-- =====================================================================
-- NAVALHA & CIA — Dados de demonstração (OPCIONAL)
-- Executado pelo install.php somente se a opção de demonstração for marcada,
-- sempre DEPOIS do seed.sql (usa os serviços e configurações dele).
-- Barbeiros, clientes, agenda, pagamentos, bloqueios, feriados e depoimentos.
-- =====================================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------- BARBEIROS
INSERT INTO `barbers` (`id`,`name`,`slug`,`headline`,`bio`,`specialties`,`phone`,`email`,`display_order`,`status`) VALUES
(1,'João Silva','joao-silva','Degradê e barba desenhada','Começou aos 17 anos numa cadeira emprestada e hoje é referência em degradê na cidade. Atende no ritmo da conversa e sai com a régua conferida.','Degradê, Barba, Corte masculino','(81) 98888-1111','joao@navalhaecia.com.br',1,1),
(2,'Pedro Ramos','pedro-ramos','Clássico, tesoura e pente','Formado na escola clássica italiana. Trabalha quase tudo na tesoura e é o nome certo para quem quer manter comprimento sem perder forma.','Corte clássico, Tesoura, Sobrancelha','(81) 98888-2222','pedro@navalhaecia.com.br',2,1),
(3,'Caio Bastos','caio-bastos','Navalha e cuidados com a barba','Especialista em barba: modelagem, hidratação e o ritual completo de toalha quente. Paciência de sobra para barba nova.','Barba, Navalha, Degradê','(81) 98888-3333','caio@navalhaecia.com.br',3,1);

-- Serviços por barbeiro
INSERT INTO `barber_services` (`barber_id`,`service_id`) VALUES
(1,1),(1,2),(1,3),(1,5),
(2,1),(2,2),(2,4),(2,5),
(3,1),(3,2),(3,3),(3,4),(3,5);


-- HORÁRIO INDIVIDUAL DOS BARBEIROS
INSERT INTO `barber_hours` (`barber_id`,`weekday`,`is_working`,`start_time`,`end_time`,`break_start`,`break_end`) VALUES
(1,0,0,NULL,NULL,NULL,NULL),
(1,1,1,'09:00:00','18:00:00','12:00:00','13:00:00'),
(1,2,1,'09:00:00','18:00:00','12:00:00','13:00:00'),
(1,3,0,NULL,NULL,NULL,NULL),
(1,4,1,'09:00:00','18:00:00','12:00:00','13:00:00'),
(1,5,1,'09:00:00','20:00:00','12:00:00','13:00:00'),
(1,6,1,'08:00:00','17:00:00',NULL,NULL),
(2,0,0,NULL,NULL,NULL,NULL),
(2,1,1,'08:00:00','17:00:00','12:00:00','13:00:00'),
(2,2,1,'08:00:00','17:00:00','12:00:00','13:00:00'),
(2,3,1,'08:00:00','17:00:00','12:00:00','13:00:00'),
(2,4,1,'08:00:00','17:00:00','12:00:00','13:00:00'),
(2,5,1,'08:00:00','18:00:00','12:00:00','13:00:00'),
(2,6,0,NULL,NULL,NULL,NULL),
(3,0,0,NULL,NULL,NULL,NULL),
(3,1,1,'10:00:00','19:00:00','13:00:00','14:00:00'),
(3,2,1,'10:00:00','19:00:00','13:00:00','14:00:00'),
(3,3,1,'10:00:00','19:00:00','13:00:00','14:00:00'),
(3,4,1,'10:00:00','19:00:00','13:00:00','14:00:00'),
(3,5,1,'10:00:00','20:00:00','13:00:00','14:00:00'),
(3,6,1,'09:00:00','18:00:00',NULL,NULL);

-- -------------------------------------------------------------- CLIENTES
INSERT INTO `customers` (`id`,`name`,`phone`,`email`) VALUES
(1,'Vinícius de Souza','(81) 99911-0001','vinicius@exemplo.com'),
(2,'Carlos Andrade','(81) 99911-0002','carlos@exemplo.com'),
(3,'Lucas Monteiro','(81) 99911-0003','lucas@exemplo.com'),
(4,'Rafael Duarte','(81) 99911-0004','rafael@exemplo.com'),
(5,'Bruno Tavares','(81) 99911-0005','bruno@exemplo.com'),
(6,'Diego Lins','(81) 99911-0006','diego@exemplo.com'),
(7,'Marcelo Pires','(81) 99911-0007','marcelo@exemplo.com'),
(8,'Henrique Sá','(81) 99911-0008','henrique@exemplo.com'),
(9,'Thiago Rocha','(81) 99911-0009','thiago@exemplo.com'),
(10,'Gustavo Neves','(81) 99911-0010','gustavo@exemplo.com');

-- --------------------------------------------------------- AGENDAMENTOS
-- Datas relativas a CURDATE() para o demo nunca ficar desatualizado.
INSERT INTO `appointments`
(`code`,`customer_id`,`barber_id`,`service_id`,`appointment_date`,`start_time`,`end_time`,`duration_min`,`price`,`status`,`notes`,`source`) VALUES
-- histórico (concluídos)
('BQ7A2K01',1,1,2,DATE_SUB(CURDATE(), INTERVAL 28 DAY),'14:00:00','15:00:00',60,70.00,'completed','Mesmo corte da última vez.','site'),
('BQ7A2K02',2,2,1,DATE_SUB(CURDATE(), INTERVAL 25 DAY),'09:00:00','09:40:00',40,40.00,'completed',NULL,'admin'),
('BQ7A2K03',3,3,3,DATE_SUB(CURDATE(), INTERVAL 21 DAY),'10:30:00','11:00:00',30,35.00,'completed',NULL,'site'),
('BQ7A2K04',4,1,5,DATE_SUB(CURDATE(), INTERVAL 18 DAY),'16:00:00','16:50:00',50,55.00,'completed',NULL,'site'),
('BQ7A2K05',5,2,1,DATE_SUB(CURDATE(), INTERVAL 14 DAY),'11:00:00','11:40:00',40,40.00,'completed',NULL,'whatsapp'),
('BQ7A2K06',6,3,2,DATE_SUB(CURDATE(), INTERVAL 12 DAY),'15:00:00','16:00:00',60,70.00,'completed',NULL,'site'),
('BQ7A2K07',1,1,1,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'09:00:00','09:40:00',40,40.00,'completed',NULL,'site'),
('BQ7A2K08',7,2,4,DATE_SUB(CURDATE(), INTERVAL 9 DAY),'14:00:00','14:15:00',15,20.00,'completed',NULL,'admin'),
('BQ7A2K09',8,3,3,DATE_SUB(CURDATE(), INTERVAL 7 DAY),'17:00:00','17:30:00',30,35.00,'completed',NULL,'site'),
('BQ7A2K10',9,1,2,DATE_SUB(CURDATE(), INTERVAL 6 DAY),'10:00:00','11:00:00',60,70.00,'no_show',NULL,'site'),
('BQ7A2K11',10,2,5,DATE_SUB(CURDATE(), INTERVAL 5 DAY),'08:30:00','09:20:00',50,55.00,'completed',NULL,'site'),
('BQ7A2K12',2,3,2,DATE_SUB(CURDATE(), INTERVAL 4 DAY),'11:00:00','12:00:00',60,70.00,'cancelled','Cliente remarcou.','site'),
('BQ7A2K13',3,1,1,DATE_SUB(CURDATE(), INTERVAL 3 DAY),'15:00:00','15:40:00',40,40.00,'completed',NULL,'site'),
('BQ7A2K14',4,2,1,DATE_SUB(CURDATE(), INTERVAL 2 DAY),'09:00:00','09:40:00',40,40.00,'completed',NULL,'phone'),
-- hoje
('BQ7A2K15',5,1,2,CURDATE(),'09:00:00','10:00:00',60,70.00,'completed',NULL,'site'),
('BQ7A2K16',6,2,1,CURDATE(),'10:00:00','10:40:00',40,40.00,'in_progress',NULL,'site'),
('BQ7A2K17',7,3,3,CURDATE(),'14:00:00','14:30:00',30,35.00,'confirmed',NULL,'site'),
('BQ7A2K18',8,1,5,CURDATE(),'16:00:00','16:50:00',50,55.00,'pending','Primeira vez na barbearia.','site'),
-- futuro
('BQ7A2K19',9,2,2,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'11:00:00','12:00:00',60,70.00,'confirmed',NULL,'site'),
('BQ7A2K20',10,3,1,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'15:00:00','15:40:00',40,40.00,'pending',NULL,'site');

-- Pagamentos dos atendimentos concluídos
INSERT INTO `payment_records` (`appointment_id`,`amount`,`method`,`paid_at`)
SELECT `id`, `price`,
       ELT(1 + (`id` % 4),'dinheiro','pix','debito','credito'),
       TIMESTAMP(`appointment_date`, `end_time`)
FROM `appointments` WHERE `status` = 'completed';

-- ------------------------------------------------------------- BLOQUEIOS
INSERT INTO `blocked_times` (`barber_id`,`block_date`,`start_time`,`end_time`,`all_day`,`reason`) VALUES
(1, DATE_ADD(CURDATE(), INTERVAL 7 DAY),'00:00:00','23:59:00',1,'Folga do João'),
(3, DATE_ADD(CURDATE(), INTERVAL 3 DAY),'15:00:00','17:00:00',0,'Compromisso pessoal'),
(NULL, DATE_ADD(CURDATE(), INTERVAL 10 DAY),'08:00:00','12:00:00',0,'Manutenção elétrica');

-- -------------------------------------------------------------- FERIADOS
INSERT INTO `holidays` (`name`,`holiday_date`,`description`,`status`) VALUES
('Natal','2026-12-25','Barbearia fechada',1),
('Ano Novo','2027-01-01','Barbearia fechada',1),
('Dia do Trabalho','2027-05-01','Barbearia fechada',1);

-- ----------------------------------------------------------- DEPOIMENTOS
INSERT INTO `testimonials` (`name`,`rating`,`content`,`posted_on`,`status`) VALUES
('Carlos Andrade',5,'Terceira vez que venho e nunca saí menos que satisfeito. O acabamento na navalha faz toda a diferença.',DATE_SUB(CURDATE(), INTERVAL 20 DAY),1),
('Lucas Monteiro',5,'Agendei pelo site em um minuto, cheguei e a cadeira estava livre. Sem espera, sem fila.',DATE_SUB(CURDATE(), INTERVAL 13 DAY),1),
('Rafael Duarte',4,'Ambiente muito bom, café enquanto espera e conversa boa. O Pedro entendeu exatamente o que eu queria.',DATE_SUB(CURDATE(), INTERVAL 8 DAY),1),
('Bruno Tavares',5,'Barba feita com calma, toalha quente e óleo no final. Vale cada centavo.',DATE_SUB(CURDATE(), INTERVAL 4 DAY),1);
