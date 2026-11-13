<?php
declare(strict_types=1);

/**
 * MOTOR DE DISPONIBILIDADE
 * ------------------------------------------------------------------
 * O backend é a fonte definitiva da disponibilidade. Nenhum horário
 * é fixo: cada slot é calculado a partir de
 *   1. horário de funcionamento da barbearia
 *   2. horário individual do barbeiro
 *   3. duração do serviço escolhido
 *   4. agendamentos já existentes
 *   5. intervalos (almoço)
 *   6. folgas e bloqueios manuais
 *   7. feriados
 *   8. data/hora atual + antecedência mínima
 */

require_once __DIR__ . '/functions.php';

// ========================================================= CONSULTAS BASE
function get_service(int $serviceId, bool $onlyActive = true): ?array
{
    $sql = 'SELECT * FROM services WHERE id = :id' . ($onlyActive ? ' AND status = 1' : '') . ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $serviceId]);
    return $stmt->fetch() ?: null;
}

function get_barber(int $barberId, bool $onlyActive = true): ?array
{
    $sql = 'SELECT * FROM barbers WHERE id = :id' . ($onlyActive ? ' AND status = 1' : '') . ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $barberId]);
    return $stmt->fetch() ?: null;
}

/** Barbeiros ativos habilitados a executar o serviço. */
function barbers_for_service(int $serviceId): array
{
    $stmt = db()->prepare(
        'SELECT b.* FROM barbers b
         INNER JOIN barber_services bs ON bs.barber_id = b.id
         WHERE bs.service_id = :sid AND b.status = 1
         ORDER BY b.display_order, b.name'
    );
    $stmt->execute([':sid' => $serviceId]);
    return $stmt->fetchAll();
}

function is_holiday(string $date): bool
{
    $stmt = db()->prepare('SELECT 1 FROM holidays WHERE holiday_date = :d AND status = 1 LIMIT 1');
    $stmt->execute([':d' => $date]);
    return (bool) $stmt->fetchColumn();
}

// ========================================================= JANELAS DE TEMPO
/**
 * Janelas de funcionamento da barbearia no dia da semana, em minutos.
 * @return array<int, array{0:int,1:int}>
 */
function business_windows(int $weekday): array
{
    static $cache = [];
    if (isset($cache[$weekday])) {
        return $cache[$weekday];
    }

    $stmt = db()->prepare('SELECT * FROM business_hours WHERE weekday = :w LIMIT 1');
    $stmt->execute([':w' => $weekday]);
    $row = $stmt->fetch();

    if (!$row || !(int) $row['is_open'] || !$row['open_time'] || !$row['close_time']) {
        return $cache[$weekday] = [];
    }

    return $cache[$weekday] = split_window(
        time_to_minutes($row['open_time']),
        time_to_minutes($row['close_time']),
        $row['break_start'] ? time_to_minutes($row['break_start']) : null,
        $row['break_end'] ? time_to_minutes($row['break_end']) : null
    );
}

/** Janelas de trabalho do barbeiro no dia da semana, em minutos. */
function barber_windows(int $barberId, int $weekday): array
{
    $stmt = db()->prepare('SELECT * FROM barber_hours WHERE barber_id = :b AND weekday = :w LIMIT 1');
    $stmt->execute([':b' => $barberId, ':w' => $weekday]);
    $row = $stmt->fetch();

    // Sem horário próprio cadastrado: segue o horário da barbearia.
    if (!$row) {
        return business_windows($weekday);
    }
    if (!(int) $row['is_working'] || !$row['start_time'] || !$row['end_time']) {
        return [];
    }

    return split_window(
        time_to_minutes($row['start_time']),
        time_to_minutes($row['end_time']),
        $row['break_start'] ? time_to_minutes($row['break_start']) : null,
        $row['break_end'] ? time_to_minutes($row['break_end']) : null
    );
}

/** Divide uma janela em duas quando há intervalo no meio. */
function split_window(int $start, int $end, ?int $breakStart, ?int $breakEnd): array
{
    if ($breakStart === null || $breakEnd === null || $breakEnd <= $breakStart) {
        return $end > $start ? [[$start, $end]] : [];
    }
    $windows = [];
    if ($breakStart > $start) {
        $windows[] = [$start, min($breakStart, $end)];
    }
    if ($breakEnd < $end) {
        $windows[] = [max($breakEnd, $start), $end];
    }
    return array_values(array_filter($windows, fn($w) => $w[1] > $w[0]));
}

/** Interseção entre as janelas da barbearia e as do barbeiro. */
function intersect_windows(array $a, array $b): array
{
    $result = [];
    foreach ($a as $wa) {
        foreach ($b as $wb) {
            $start = max($wa[0], $wb[0]);
            $end   = min($wa[1], $wb[1]);
            if ($end > $start) {
                $result[] = [$start, $end];
            }
        }
    }
    usort($result, fn($x, $y) => $x[0] <=> $y[0]);
    return $result;
}

// ====================================================== INTERVALOS OCUPADOS
/**
 * Tudo que ocupa a agenda do barbeiro na data: agendamentos ativos
 * e bloqueios (do barbeiro ou gerais da barbearia).
 * @return array<int, array{0:int,1:int}>
 */
function busy_intervals(int $barberId, string $date, ?int $ignoreAppointmentId = null): array
{
    $busy = [];

    $statuses = blocking_statuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$barberId, $date], $statuses);

    $sql = "SELECT start_time, end_time FROM appointments
            WHERE barber_id = ? AND appointment_date = ? AND status IN ($in)";
    if ($ignoreAppointmentId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreAppointmentId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $busy[] = [time_to_minutes($row['start_time']), time_to_minutes($row['end_time'])];
    }

    $stmt = db()->prepare(
        'SELECT start_time, end_time, all_day FROM blocked_times
         WHERE block_date = :d AND (barber_id = :b OR barber_id IS NULL)'
    );
    $stmt->execute([':d' => $date, ':b' => $barberId]);
    foreach ($stmt->fetchAll() as $row) {
        $busy[] = (int) $row['all_day']
            ? [0, 1440]
            : [time_to_minutes($row['start_time']), time_to_minutes($row['end_time'])];
    }

    return $busy;
}

function overlaps(int $start, int $end, array $intervals): bool
{
    foreach ($intervals as [$bs, $be]) {
        if ($start < $be && $end > $bs) {
            return true;
        }
    }
    return false;
}

// ============================================================ SLOTS LIVRES
/**
 * Horários livres de UM barbeiro para UM serviço em UMA data.
 * @return string[] ex.: ['09:00','09:30','14:00']
 */
function slots_for_barber(int $barberId, int $serviceId, string $date, ?int $ignoreAppointmentId = null): array
{
    $service = get_service($serviceId);
    if (!$service) {
        return [];
    }

    // O barbeiro executa esse serviço?
    $stmt = db()->prepare('SELECT 1 FROM barber_services WHERE barber_id = :b AND service_id = :s LIMIT 1');
    $stmt->execute([':b' => $barberId, ':s' => $serviceId]);
    if (!$stmt->fetchColumn()) {
        return [];
    }

    if (!date_is_bookable($date)) {
        return [];
    }

    $weekday = (int) date('w', strtotime($date));
    $windows = intersect_windows(business_windows($weekday), barber_windows($barberId, $weekday));
    if (!$windows) {
        return [];
    }

    $duration = (int) $service['duration_min'];
    $step     = max(5, (int) setting('slot_step', '15'));
    $busy     = busy_intervals($barberId, $date, $ignoreAppointmentId);

    // Antecedência mínima quando a data é hoje
    $floor = -1;
    if ($date === date('Y-m-d')) {
        $floor = time_to_minutes(date('H:i')) + (int) setting('min_advance_minutes', '60');
    }

    $slots = [];
    foreach ($windows as [$wStart, $wEnd]) {
        for ($t = $wStart; $t + $duration <= $wEnd; $t += $step) {
            if ($t < $floor) {
                continue;
            }
            if (overlaps($t, $t + $duration, $busy)) {
                continue;
            }
            $slots[] = minutes_to_time($t);
        }
    }

    return array_values(array_unique($slots));
}

/** A data está dentro da janela agendável (não passada, não feriado, dentro do limite)? */
function date_is_bookable(string $date): bool
{
    $today = date('Y-m-d');
    if ($date < $today) {
        return false;
    }
    $maxDays = (int) setting('max_advance_days', '60');
    if ($date > date('Y-m-d', strtotime("+{$maxDays} days"))) {
        return false;
    }
    return !is_holiday($date);
}

/**
 * Disponibilidade consolidada para a tela de agendamento.
 * Com $barberId = null, agrega todos os barbeiros aptos e sugere,
 * para cada horário, o barbeiro com menos atendimentos no dia.
 *
 * @return array{slots: array<int, array{time:string, barber_id:int, barber_name:string}>, closed_reason: ?string}
 */
function availability(int $serviceId, ?int $barberId, string $date): array
{
    $service = get_service($serviceId);
    if (!$service) {
        return ['slots' => [], 'closed_reason' => 'Serviço indisponível.'];
    }
    if ($date < date('Y-m-d')) {
        return ['slots' => [], 'closed_reason' => 'Essa data já passou.'];
    }
    if (is_holiday($date)) {
        return ['slots' => [], 'closed_reason' => 'Feriado — a barbearia estará fechada.'];
    }
    if (!business_windows((int) date('w', strtotime($date)))) {
        return ['slots' => [], 'closed_reason' => 'A barbearia não abre neste dia.'];
    }

    $barbers = $barberId ? array_filter(barbers_for_service($serviceId), fn($b) => (int) $b['id'] === $barberId)
                         : barbers_for_service($serviceId);
    if (!$barbers) {
        return ['slots' => [], 'closed_reason' => 'Nenhum barbeiro disponível para esse serviço.'];
    }

    $load = day_load($date);
    $map  = []; // "HH:MM" => barbeiro escolhido

    foreach ($barbers as $barber) {
        $bid = (int) $barber['id'];
        foreach (slots_for_barber($bid, $serviceId, $date) as $time) {
            $currentLoad = $load[$bid] ?? 0;
            if (!isset($map[$time]) || $currentLoad < $map[$time]['load']) {
                $map[$time] = [
                    'time'        => $time,
                    'barber_id'   => $bid,
                    'barber_name' => $barber['name'],
                    'load'        => $currentLoad,
                ];
            }
        }
    }

    ksort($map);
    $slots = array_values(array_map(function ($s) {
        unset($s['load']);
        return $s;
    }, $map));

    $reason = $slots ? null : 'Sem horários livres nesta data. Tente outro dia.';
    return ['slots' => $slots, 'closed_reason' => $reason];
}

/** Quantidade de atendimentos ativos por barbeiro em uma data. */
function day_load(string $date): array
{
    $statuses = blocking_statuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = db()->prepare(
        "SELECT barber_id, COUNT(*) AS total FROM appointments
         WHERE appointment_date = ? AND status IN ($in) GROUP BY barber_id"
    );
    $stmt->execute(array_merge([$date], $statuses));

    $load = [];
    foreach ($stmt->fetchAll() as $row) {
        $load[(int) $row['barber_id']] = (int) $row['total'];
    }
    return $load;
}

/** Dias do mês que têm pelo menos um horário livre — usado pelo calendário. */
function bookable_days(int $serviceId, ?int $barberId, int $year, int $month): array
{
    $days  = [];
    $first = sprintf('%04d-%02d-01', $year, $month);
    $last  = date('Y-m-t', strtotime($first));

    for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        if (!date_is_bookable($d)) {
            continue;
        }
        $result = availability($serviceId, $barberId, $d);
        if ($result['slots']) {
            $days[] = $d;
        }
    }
    return $days;
}

// =========================================================== GRAVAR RESERVA
/**
 * Cria o agendamento dentro de uma transação, revalidando a
 * disponibilidade com bloqueio de linha para evitar reserva dupla.
 *
 * @throws RuntimeException quando o horário não está mais livre
 */
function create_appointment(array $data): array
{
    $pdo = db();

    $service = get_service((int) $data['service_id']);
    if (!$service) {
        throw new RuntimeException('Serviço indisponível.');
    }
    $barber = get_barber((int) $data['barber_id']);
    if (!$barber) {
        throw new RuntimeException('Barbeiro indisponível.');
    }

    $date     = $data['date'];
    $start    = substr($data['time'], 0, 5);
    $duration = (int) $service['duration_min'];
    $end      = minutes_to_time(time_to_minutes($start) + $duration);

    $pdo->beginTransaction();

    try {
        // Bloqueia a faixa da agenda desse barbeiro no dia.
        $stmt = $pdo->prepare(
            'SELECT id FROM appointments
             WHERE barber_id = :b AND appointment_date = :d FOR UPDATE'
        );
        $stmt->execute([':b' => (int) $data['barber_id'], ':d' => $date]);

        // Revalida com os dados já bloqueados.
        $free = slots_for_barber((int) $data['barber_id'], (int) $data['service_id'], $date);
        if (!in_array($start, $free, true)) {
            $pdo->rollBack();
            throw new RuntimeException('Este horário acabou de ser reservado. Escolha outro horário.');
        }

        $customerId = (int) $data['customer_id'];

        // Código único (com nova tentativa em caso de colisão)
        for ($i = 0; $i < 5; $i++) {
            $code = generate_appointment_code();
            $check = $pdo->prepare('SELECT 1 FROM appointments WHERE code = :c LIMIT 1');
            $check->execute([':c' => $code]);
            if (!$check->fetchColumn()) {
                break;
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO appointments
             (code, customer_id, barber_id, service_id, appointment_date, start_time, end_time,
              duration_min, price, status, notes, source, created_by)
             VALUES (:code, :customer, :barber, :service, :date, :start, :end,
              :duration, :price, :status, :notes, :source, :created_by)'
        );
        $insert->execute([
            ':code'       => $code,
            ':customer'   => $customerId,
            ':barber'     => (int) $data['barber_id'],
            ':service'    => (int) $data['service_id'],
            ':date'       => $date,
            ':start'      => $start,
            ':end'        => $end,
            ':duration'   => $duration,
            ':price'      => $service['price'],
            ':status'     => $data['status'] ?? 'pending',
            ':notes'      => $data['notes'] ?? null,
            ':source'     => $data['source'] ?? 'site',
            ':created_by' => $data['created_by'] ?? null,
        ]);

        $appointmentId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, user_id, note)
             VALUES (:a, NULL, :s, :u, :n)'
        )->execute([
            ':a' => $appointmentId,
            ':s' => $data['status'] ?? 'pending',
            ':u' => $data['created_by'] ?? null,
            ':n' => 'Agendamento criado',
        ]);

        $pdo->commit();
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('Não foi possível concluir o agendamento. Tente novamente.');
    }

    return get_appointment($appointmentId);
}

/** Busca um agendamento completo (com cliente, barbeiro e serviço). */
function get_appointment(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                b.name AS barber_name, s.name AS service_name
         FROM appointments a
         JOIN customers c ON c.id = a.customer_id
         JOIN barbers   b ON b.id = a.barber_id
         JOIN services  s ON s.id = a.service_id
         WHERE a.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function get_appointment_by_code(string $code): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                b.name AS barber_name, s.name AS service_name
         FROM appointments a
         JOIN customers c ON c.id = a.customer_id
         JOIN barbers   b ON b.id = a.barber_id
         JOIN services  s ON s.id = a.service_id
         WHERE a.code = :code LIMIT 1'
    );
    $stmt->execute([':code' => strtoupper($code)]);
    return $stmt->fetch() ?: null;
}

/** Encontra ou cria o cliente a partir do telefone. */
function find_or_create_customer(string $name, string $phone, ?string $email): int
{
    $phone = format_phone($phone);

    $stmt = db()->prepare('SELECT id FROM customers WHERE phone = :p LIMIT 1');
    $stmt->execute([':p' => $phone]);
    $id = $stmt->fetchColumn();

    if ($id) {
        db()->prepare('UPDATE customers SET name = :n, email = COALESCE(NULLIF(:e, \'\'), email) WHERE id = :id')
            ->execute([':n' => $name, ':e' => $email, ':id' => $id]);
        return (int) $id;
    }

    $insert = db()->prepare('INSERT INTO customers (name, phone, email) VALUES (:n, :p, :e)');
    $insert->execute([':n' => $name, ':p' => $phone, ':e' => $email ?: null]);
    return (int) db()->lastInsertId();
}

/** O agendamento ainda pode ser cancelado online? */
function can_cancel(array $appointment): bool
{
    if (in_array($appointment['status'], ['cancelled', 'completed', 'no_show'], true)) {
        return false;
    }
    $limit = (int) setting('cancel_limit_hours', '24');
    $startsAt = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
    return $startsAt - time() >= $limit * 3600;
}

/** Último erro de change_status() (para mostrar o motivo real no painel). */
function status_error(): ?string
{
    return $GLOBALS['status_error'] ?? null;
}

/** Altera o status registrando o histórico. */
function change_status(int $appointmentId, string $newStatus, ?int $userId = null, ?string $note = null): bool
{
    $GLOBALS['status_error'] = null;

    if (!array_key_exists($newStatus, appointment_statuses())) {
        $GLOBALS['status_error'] = 'Situação inválida.';
        return false;
    }
    $current = get_appointment($appointmentId);
    if (!$current) {
        $GLOBALS['status_error'] = 'Agendamento não encontrado.';
        return false;
    }
    if ($current['status'] === $newStatus) {
        $GLOBALS['status_error'] = 'O agendamento já está com essa situação.';
        return false;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // SQL simples e sem parâmetros dentro de funções (mais compatível entre MySQL/MariaDB)
        $sql = $newStatus === 'cancelled'
            ? 'UPDATE appointments SET status = :s, cancelled_at = NOW() WHERE id = :id'
            : 'UPDATE appointments SET status = :s WHERE id = :id';
        $pdo->prepare($sql)->execute([':s' => $newStatus, ':id' => $appointmentId]);

        $pdo->prepare(
            'INSERT INTO appointment_status_history (appointment_id, from_status, to_status, user_id, note)
             VALUES (:a, :f, :t, :u, :n)'
        )->execute([
            ':a' => $appointmentId, ':f' => $current['status'], ':t' => $newStatus,
            ':u' => $userId, ':n' => $note,
        ]);

        // Registra o pagamento quando o atendimento é concluído
        if ($newStatus === 'completed') {
            $exists = $pdo->prepare('SELECT 1 FROM payment_records WHERE appointment_id = :a LIMIT 1');
            $exists->execute([':a' => $appointmentId]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare('INSERT INTO payment_records (appointment_id, amount) VALUES (:a, :v)')
                    ->execute([':a' => $appointmentId, ':v' => $current['price']]);
            }
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[change_status] ' . $e->getMessage());
        $GLOBALS['status_error'] = $e->getMessage();
        return false;
    }
}
