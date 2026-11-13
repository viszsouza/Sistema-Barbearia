-- =====================================================================
-- NAVALHA & CIA — Sistema de Gestão e Agendamento para Barbearia
-- Schema MySQL 5.7+ / MariaDB 10.3+
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Usuários administrativos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)  NOT NULL,
  `email`         VARCHAR(160)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('super_admin','admin','receptionist') NOT NULL DEFAULT 'receptionist',
  `status`        TINYINT(1)    NOT NULL DEFAULT 1,
  `last_login_at` DATETIME      NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Clientes (login opcional: password_hash pode ser NULL)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `phone`         VARCHAR(20)  NOT NULL,
  `email`         VARCHAR(160) NULL,
  `password_hash` VARCHAR(255) NULL,
  `notes`         TEXT         NULL,
  `status`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_phone` (`phone`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `ix_customers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Barbeiros
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `barbers`;
CREATE TABLE `barbers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `slug`          VARCHAR(140) NOT NULL,
  `photo`         VARCHAR(255) NULL,
  `headline`      VARCHAR(160) NULL,
  `bio`           TEXT         NULL,
  `specialties`   VARCHAR(255) NULL,
  `phone`         VARCHAR(20)  NULL,
  `email`         VARCHAR(160) NULL,
  `display_order` SMALLINT     NOT NULL DEFAULT 0,
  `status`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_barbers_slug` (`slug`),
  KEY `ix_barbers_status_order` (`status`,`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Categorias e serviços
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `service_categories`;
CREATE TABLE `service_categories` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `display_order` SMALLINT     NOT NULL DEFAULT 0,
  `status`        TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`   INT UNSIGNED NULL,
  `name`          VARCHAR(120)   NOT NULL,
  `slug`          VARCHAR(140)   NOT NULL,
  `description`   TEXT           NULL,
  `price`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `duration_min`  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `image`         VARCHAR(255)   NULL,
  `icon`          VARCHAR(40)    NULL,
  `display_order` SMALLINT       NOT NULL DEFAULT 0,
  `status`        TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_slug` (`slug`),
  KEY `ix_services_status_order` (`status`,`display_order`),
  CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`)
    REFERENCES `service_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quais serviços cada barbeiro executa
DROP TABLE IF EXISTS `barber_services`;
CREATE TABLE `barber_services` (
  `barber_id`  INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`barber_id`,`service_id`),
  KEY `ix_bs_service` (`service_id`),
  CONSTRAINT `fk_bs_barber`  FOREIGN KEY (`barber_id`)  REFERENCES `barbers` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_bs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Horário de funcionamento da barbearia (0 = domingo ... 6 = sábado)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `business_hours`;
CREATE TABLE `business_hours` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `weekday`     TINYINT UNSIGNED NOT NULL,
  `is_open`     TINYINT(1) NOT NULL DEFAULT 1,
  `open_time`   TIME NULL,
  `close_time`  TIME NULL,
  `break_start` TIME NULL,
  `break_end`   TIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bh_weekday` (`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horário individual do barbeiro (sobrepõe o da barbearia por interseção)
DROP TABLE IF EXISTS `barber_hours`;
CREATE TABLE `barber_hours` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barber_id`   INT UNSIGNED NOT NULL,
  `weekday`     TINYINT UNSIGNED NOT NULL,
  `is_working`  TINYINT(1) NOT NULL DEFAULT 1,
  `start_time`  TIME NULL,
  `end_time`    TIME NULL,
  `break_start` TIME NULL,
  `break_end`   TIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_barber_weekday` (`barber_id`,`weekday`),
  CONSTRAINT `fk_barber_hours_barber` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Bloqueios (barber_id NULL = bloqueio geral da barbearia)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `blocked_times`;
CREATE TABLE `blocked_times` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barber_id`   INT UNSIGNED NULL,
  `block_date`  DATE NOT NULL,
  `start_time`  TIME NOT NULL DEFAULT '00:00:00',
  `end_time`    TIME NOT NULL DEFAULT '23:59:00',
  `all_day`     TINYINT(1) NOT NULL DEFAULT 0,
  `reason`      VARCHAR(180) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_blocked_date` (`block_date`,`barber_id`),
  CONSTRAINT `fk_blocked_barber` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Feriados
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `holidays`;
CREATE TABLE `holidays` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120) NOT NULL,
  `holiday_date` DATE NOT NULL,
  `description`  VARCHAR(255) NULL,
  `status`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Agendamentos
-- O índice único impede dois agendamentos ativos no mesmo início/barbeiro.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(12)  NOT NULL,
  `customer_id`      INT UNSIGNED NOT NULL,
  `barber_id`        INT UNSIGNED NOT NULL,
  `service_id`       INT UNSIGNED NOT NULL,
  `appointment_date` DATE NOT NULL,
  `start_time`       TIME NOT NULL,
  `end_time`         TIME NOT NULL,
  `duration_min`     SMALLINT UNSIGNED NOT NULL,
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`           ENUM('pending','confirmed','in_progress','completed','cancelled','no_show')
                     NOT NULL DEFAULT 'pending',
  `notes`            TEXT NULL,
  `source`           ENUM('site','admin','whatsapp','phone') NOT NULL DEFAULT 'site',
  `created_by`       INT UNSIGNED NULL,
  `cancelled_at`     DATETIME NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointment_code` (`code`),
  KEY `ix_appt_date_barber`  (`appointment_date`,`barber_id`,`start_time`),
  KEY `ix_appt_status`       (`status`,`appointment_date`),
  KEY `ix_appt_customer`     (`customer_id`,`appointment_date`),
  KEY `ix_appt_service`      (`service_id`),
  CONSTRAINT `fk_appt_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_barber`   FOREIGN KEY (`barber_id`)   REFERENCES `barbers` (`id`)   ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_service`  FOREIGN KEY (`service_id`)  REFERENCES `services` (`id`)  ON DELETE RESTRICT,
  CONSTRAINT `fk_appt_user`     FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico de mudanças de status
DROP TABLE IF EXISTS `appointment_status_history`;
CREATE TABLE `appointment_status_history` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NOT NULL,
  `from_status`    VARCHAR(20) NULL,
  `to_status`      VARCHAR(20) NOT NULL,
  `user_id`        INT UNSIGNED NULL,
  `note`           VARCHAR(255) NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_hist_appointment` (`appointment_id`),
  CONSTRAINT `fk_hist_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pagamentos
DROP TABLE IF EXISTS `payment_records`;
CREATE TABLE `payment_records` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `method`         ENUM('dinheiro','pix','debito','credito','outro') NOT NULL DEFAULT 'dinheiro',
  `paid_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pay_appointment` (`appointment_id`),
  CONSTRAINT `fk_pay_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Fila de notificações (e-mail implementado; WhatsApp/SMS preparados)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NULL,
  `channel`        ENUM('email','whatsapp','sms') NOT NULL DEFAULT 'email',
  `event`          VARCHAR(40) NOT NULL,
  `recipient`      VARCHAR(180) NOT NULL,
  `subject`        VARCHAR(200) NULL,
  `body`           TEXT NULL,
  `status`         ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  `error`          VARCHAR(255) NULL,
  `sent_at`        DATETIME NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notif_status` (`status`,`channel`),
  CONSTRAINT `fk_notif_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Depoimentos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE `testimonials` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `photo`      VARCHAR(255) NULL,
  `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `content`    TEXT NOT NULL,
  `posted_on`  DATE NULL,
  `status`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_testimonials_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações (chave/valor)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
