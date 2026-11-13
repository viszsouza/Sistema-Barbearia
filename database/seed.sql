-- =====================================================================
-- NAVALHA & CIA — Dados iniciais (instalação limpa)
-- Executado pelo install.php depois do schema.sql.
-- Contém apenas: Configurações, Serviços (com categorias) e o horário
-- de funcionamento. Barbeiros, clientes e agenda começam vazios.
-- Não contém usuários administrativos: eles são criados no install.
-- =====================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- CONFIG
INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('business_name','Navalha & Cia'),
('business_tagline','Barbearia clássica desde 2014'),
('business_logo',''),
('business_phone','(81) 3456-7890'),
('business_whatsapp','5581999998888'),
('business_email','contato@navalhaecia.com.br'),
('business_address','Rua da Aurora, 482 — Boa Vista, Recife/PE'),
('business_city','Recife'),
('business_state','PE'),
('business_maps_embed','https://www.google.com/maps?q=Rua%20da%20Aurora%20Recife&output=embed'),
('social_instagram','https://instagram.com/navalhaecia'),
('social_facebook','https://facebook.com/navalhaecia'),
('slot_step','15'),
('min_advance_minutes','60'),
('max_advance_days','60'),
('cancel_limit_hours','24'),
('confirmation_message','Seu horário está reservado. Chegue com 5 minutos de antecedência.'),
('cancellation_message','Seu agendamento foi cancelado. Esperamos ver você em breve.'),
('stat_clients','4200'),
('stat_years','12'),
('notify_email_enabled','1'),
('notify_whatsapp_enabled','0');

-- ------------------------------------------------------------ CATEGORIAS
INSERT INTO `service_categories` (`id`,`name`,`display_order`) VALUES
(1,'Cabelo',1),(2,'Barba',2),(3,'Combos',3),(4,'Cuidados',4);

-- -------------------------------------------------------------- SERVIÇOS
INSERT INTO `services` (`id`,`category_id`,`name`,`slug`,`description`,`price`,`duration_min`,`icon`,`display_order`,`status`) VALUES
(1,1,'Corte Tradicional','corte-tradicional','Tesoura e máquina, acabamento na navalha e finalização com toalha quente.',40.00,40,'scissors',1,1),
(2,3,'Corte + Barba','corte-barba','O pacote completo: corte sob medida, barba feita na navalha e toalha quente.',70.00,60,'combo',2,1),
(3,2,'Barba','barba','Modelagem, navalha, toalha quente e óleo finalizador.',35.00,30,'razor',3,1),
(4,4,'Sobrancelha','sobrancelha','Alinhamento na navalha ou pinça, respeitando o traço natural.',20.00,15,'brow',4,1),
(5,1,'Degradê Navalhado','degrade-navalhado','Transição milimétrica do zero ao topo, finalizada na navalha.',55.00,50,'fade',5,1);


-- ------------------------------------------------- HORÁRIO DE FUNCIONAMENTO
-- weekday: 0 = domingo ... 6 = sábado
INSERT INTO `business_hours` (`weekday`,`is_open`,`open_time`,`close_time`,`break_start`,`break_end`) VALUES
(0,0,NULL,NULL,NULL,NULL),
(1,1,'08:00:00','19:00:00','12:00:00','13:00:00'),
(2,1,'08:00:00','19:00:00','12:00:00','13:00:00'),
(3,1,'08:00:00','19:00:00','12:00:00','13:00:00'),
(4,1,'08:00:00','19:00:00','12:00:00','13:00:00'),
(5,1,'08:00:00','20:00:00','12:00:00','13:00:00'),
(6,1,'08:00:00','18:00:00',NULL,NULL);

