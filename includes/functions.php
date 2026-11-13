<?php
declare(strict_types=1);

// ============================================================== SAÍDA SEGURA
/** Escapa para HTML. Use em TODA saída dinâmica. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Resposta JSON e encerramento. */
function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

// ======================================================================= CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $token = null): bool
{
    $token ??= $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Aborta a requisição se o token for inválido. */
function csrf_guard(bool $json = false): void
{
    if (!csrf_check()) {
        if ($json) {
            json_error('Sessão expirada. Recarregue a página e tente novamente.', 419);
        }
        http_response_code(419);
        exit('Token de segurança inválido. Volte e tente novamente.');
    }
}

// ================================================================== ENTRADA
/** Lê e limpa um campo de texto. */
function input(string $key, $default = null, ?array $source = null)
{
    $source ??= $_POST;
    $value = $source[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

/** Lê o corpo JSON de uma requisição de API. */
function json_input(): array
{
    static $data = null;
    if ($data === null) {
        $raw  = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
    }
    return $data;
}

function is_valid_email(?string $email): bool
{
    return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
}

/** Valida telefone brasileiro (10 ou 11 dígitos). */
function is_valid_phone(?string $phone): bool
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    return strlen((string) $digits) >= 10 && strlen((string) $digits) <= 11;
}

function format_phone(?string $phone): string
{
    $d = preg_replace('/\D+/', '', (string) $phone) ?? '';
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string) $phone;
}

function only_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?? '';
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '');
    return trim($text, '-') ?: 'item';
}

// =============================================================== FORMATAÇÃO
function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_date(?string $date): string
{
    if (!$date) return '—';
    $d = date_create($date);
    return $d ? $d->format('d/m/Y') : '—';
}

function format_time(?string $time): string
{
    return $time ? substr($time, 0, 5) : '—';
}

function format_duration(int $minutes): string
{
    if ($minutes < 60) return $minutes . ' min';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m ? "{$h}h{$m}min" : "{$h}h";
}

function weekday_name(int $weekday, bool $short = false): string
{
    $full  = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    $abbr  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    return ($short ? $abbr : $full)[$weekday] ?? '';
}

function month_name(int $month): string
{
    $names = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    return $names[$month] ?? '';
}

function format_date_long(?string $date): string
{
    if (!$date) return '—';
    $d = date_create($date);
    if (!$d) return '—';
    return weekday_name((int) $d->format('w')) . ', ' . $d->format('d') . ' de ' . month_name((int) $d->format('n'));
}

// =============================================================== STATUS
function appointment_statuses(): array
{
    return [
        'pending'     => 'Agendado',
        'confirmed'   => 'Confirmado',
        'in_progress' => 'Em atendimento',
        'completed'   => 'Concluído',
        'cancelled'   => 'Cancelado',
        'no_show'     => 'Não compareceu',
    ];
}

function status_label(string $status): string
{
    return appointment_statuses()[$status] ?? $status;
}

/** Status que ocupam a agenda (bloqueiam o horário). */
function blocking_statuses(): array
{
    return ['pending', 'confirmed', 'in_progress', 'completed'];
}

// ============================================================ CONFIGURAÇÕES
/** Lê uma configuração (com cache em memória). */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/** Grava/atualiza uma configuração. */
function set_setting(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
}

// ===================================================================== FLASH
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function flash_pull(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function render_flash(): string
{
    $html = '';
    foreach (flash_pull() as $item) {
        $html .= '<div class="alert alert--' . e($item['type']) . '" role="status">' . e($item['message']) . '</div>';
    }
    return $html;
}

// ================================================================ REDIRECT
function redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit;
}

// ============================================================ CÓDIGO ÚNICO
/** Gera um código de agendamento legível (sem caracteres ambíguos). */
function generate_appointment_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

/** Link do WhatsApp com mensagem pré-preenchida. */
function whatsapp_link(string $message = ''): string
{
    $number = only_digits((string) setting('business_whatsapp', ''));
    return 'https://wa.me/' . $number . ($message ? '?text=' . rawurlencode($message) : '');
}

/** Converte "HH:MM[:SS]" em minutos desde a meia-noite. */
function time_to_minutes(string $time): int
{
    [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);
    return $h * 60 + $m;
}

/** Converte minutos desde a meia-noite em "HH:MM". */
function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}
