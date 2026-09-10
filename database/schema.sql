-- Bu dosya stok_takip veritabaninin tablo semasini sifirdan olusturur.
-- Ornek/seed verileri bu dosyada degil, seed.sql dosyasinda bulunmalidir.
-- Toplam Tablo Sayisi: 53

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `token_prefix` varchar(10) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `permissions` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_api_token_hash` (`token_hash`),
  KEY `idx_api_token_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `table_name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `record_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_audit_logs_user` (`user_id`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_employee_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_code` (`code`),
  KEY `idx_departments_manager` (`manager_employee_id`),
  KEY `idx_departments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `downtime_reasons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('PLANNED','UNPLANNED','SETUP','MICRO_STOP') NOT NULL DEFAULT 'UNPLANNED',
  `is_planned` tinyint(1) NOT NULL DEFAULT '0',
  `color_hex` varchar(10) DEFAULT '#ef4444',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `energy_shifts` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_code` (`code`),
  KEY `idx_shift_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `department_id` bigint unsigned NOT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_positions_department` (`department_id`),
  KEY `idx_positions_active` (`is_active`),
  CONSTRAINT `fk_positions_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `registration_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned NOT NULL,
  `phone` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_type` enum('FULL_TIME','PART_TIME','CONTRACTOR','INTERN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FULL_TIME',
  `status` enum('ACTIVE','ON_LEAVE','TERMINATED','SUSPENDED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `hire_date` date NOT NULL,
  `termination_date` date DEFAULT NULL,
  `default_shift_id` tinyint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employees_registration_no` (`registration_no`),
  UNIQUE KEY `uq_employees_user_id` (`user_id`),
  KEY `idx_employees_department` (`department_id`),
  KEY `idx_employees_position` (`position_id`),
  KEY `idx_employees_default_shift` (`default_shift_id`),
  KEY `idx_employees_status` (`status`),
  KEY `idx_employees_active` (`is_active`),
  CONSTRAINT `fk_employees_default_shift` FOREIGN KEY (`default_shift_id`) REFERENCES `energy_shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `shift_id` tinyint unsigned NOT NULL,
  `assigned_date` date NOT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emp_shift_assignment` (`employee_id`,`assigned_date`),
  KEY `idx_emp_shifts_date` (`assigned_date`),
  KEY `idx_emp_shifts_shift` (`shift_id`),
  CONSTRAINT `fk_emp_shifts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_emp_shifts_shift` FOREIGN KEY (`shift_id`) REFERENCES `energy_shifts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_facility_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL COMMENT 'Mevcut warehouses.id ile mantıksal ilişki (MyISAM)',
  `zone_type` enum('PRODUCTION','AUXILIARY','SOLAR_PLANT','OFFICE','MAIN_TRANSFORMER') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRODUCTION',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zone_code` (`code`),
  KEY `idx_zone_type_active` (`zone_type`,`is_active`),
  KEY `idx_zone_warehouse` (`warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `production_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nominal_power_kw` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('IDLE','RUNNING','MAINTENANCE','FAULT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IDLE',
  `status_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_line_code` (`code`),
  KEY `idx_line_zone_active` (`zone_id`,`is_active`),
  CONSTRAINT `fk_lines_zone` FOREIGN KEY (`zone_id`) REFERENCES `energy_facility_zones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_meters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zone_id` bigint unsigned NOT NULL,
  `line_id` bigint unsigned DEFAULT NULL,
  `parent_meter_id` bigint unsigned DEFAULT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meter_type` enum('GRID_MAIN','SOLAR_INVERTER','PRODUCTION_SUBMETER','COMPENSATION_PANEL','AUXILIARY') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bus_address` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meter_code` (`code`),
  KEY `idx_meter_type_active` (`meter_type`,`is_active`),
  KEY `idx_meter_zone` (`zone_id`),
  KEY `idx_meter_line` (`line_id`),
  KEY `idx_meter_parent` (`parent_meter_id`),
  CONSTRAINT `fk_meters_line` FOREIGN KEY (`line_id`) REFERENCES `production_lines` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_meters_parent` FOREIGN KEY (`parent_meter_id`) REFERENCES `energy_meters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_meters_zone` FOREIGN KEY (`zone_id`) REFERENCES `energy_facility_zones` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meter_id` bigint unsigned NOT NULL,
  `alert_type` enum('REACTIVE_INDUCTIVE_LIMIT','REACTIVE_CAPACITIVE_LIMIT','PEAK_POWER_SURGE','SOLAR_UNDERPERFORMANCE','IDLE_CONSUMPTION','VOLTAGE_ANOMALY','THRESHOLD_EXCEEDED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WARNING',
  `threshold_value` decimal(12,4) NOT NULL,
  `measured_value` decimal(12,4) NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT '0',
  `acknowledged_by` bigint unsigned DEFAULT NULL COMMENT 'Mevcut users.id ile mantıksal ilişki (MyISAM)',
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_meter` (`meter_id`),
  KEY `idx_alert_severity_ack` (`severity`,`is_acknowledged`,`created_at`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_alert_user` (`acknowledged_by`),
  CONSTRAINT `fk_alerts_meter` FOREIGN KEY (`meter_id`) REFERENCES `energy_meters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_production_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `line_id` bigint unsigned NOT NULL,
  `shift_id` tinyint unsigned NOT NULL,
  `log_date` date NOT NULL,
  `panels_produced_qty` int unsigned NOT NULL DEFAULT '0' COMMENT 'Üretilen panel adedi',
  `total_wp_produced` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Üretilen toplam Wp gücü',
  `scrap_panels_qty` int unsigned NOT NULL DEFAULT '0' COMMENT 'Fire panel adedi',
  `cells_used_qty` decimal(12,3) NOT NULL DEFAULT '0.000' COMMENT 'Kullanılan solar hücre',
  `stock_movement_ref` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mevcut stock_movements.reference_no referansı',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_log_date_line` (`log_date`,`line_id`),
  KEY `idx_prod_log_shift` (`shift_id`),
  KEY `idx_prod_log_ref` (`stock_movement_ref`),
  KEY `fk_prod_logs_line` (`line_id`),
  CONSTRAINT `fk_prod_logs_line` FOREIGN KEY (`line_id`) REFERENCES `production_lines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_logs_shift` FOREIGN KEY (`shift_id`) REFERENCES `energy_shifts` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_readings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meter_id` bigint unsigned NOT NULL,
  `read_at` datetime NOT NULL,
  `tariff_period` enum('T1','T2','T3') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'T1',
  `shift_id` tinyint unsigned DEFAULT NULL,
  `active_import_kwh` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT 'Bu aralıkta şebekeden çekilen tüketim (kWh)',
  `active_export_kwh` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT 'Bu aralıkta Çatı GES üretimi (kWh)',
  `reactive_inductive_kvarh` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT 'Bu aralıkta endüktif reaktif (kvarh)',
  `reactive_capacitive_kvarh` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT 'Bu aralıkta kapasitif reaktif (kvarh)',
  `cumulative_import_kwh` decimal(16,4) DEFAULT NULL COMMENT 'Sayaç kümülatif tüketim endeksi',
  `cumulative_export_kwh` decimal(16,4) DEFAULT NULL COMMENT 'Sayaç kümülatif üretim endeksi',
  `active_power_kw` decimal(10,3) NOT NULL DEFAULT '0.000' COMMENT 'Anlık aktif güç (kW)',
  `power_factor` decimal(4,3) NOT NULL DEFAULT '1.000' COMMENT 'Güç faktörü (0.000-1.000)',
  `voltage_v` decimal(6,2) DEFAULT NULL COMMENT 'Ortalama gerilim (V)',
  `current_a` decimal(8,2) DEFAULT NULL COMMENT 'Ortalama akım (A)',
  `cost_amount` decimal(12,4) NOT NULL DEFAULT '0.0000' COMMENT 'Hesaplanan parasal tutar',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meter_read_at` (`meter_id`,`read_at`),
  KEY `idx_read_at_period` (`read_at`,`tariff_period`),
  KEY `idx_meter_read_at` (`meter_id`,`read_at`),
  KEY `idx_shift_read_at` (`shift_id`,`read_at`),
  CONSTRAINT `fk_readings_meter` FOREIGN KEY (`meter_id`) REFERENCES `energy_meters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_readings_shift` FOREIGN KEY (`shift_id`) REFERENCES `energy_shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `energy_tariffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `currency` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `unit_price_t1` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Gündüz 06:00-17:00 TL/kWh',
  `unit_price_t2` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Puant 17:00-22:00 TL/kWh',
  `unit_price_t3` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Gece 22:00-06:00 TL/kWh',
  `distribution_price` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Dağıtım/İletim TL/kWh',
  `reactive_penalty_price` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Reaktif Ceza TL/kvarh',
  `solar_feed_in_tariff` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'Şebekeye verilen solar elektrik satış birim fiyatı',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tariff_code` (`code`),
  KEY `idx_tariff_dates_active` (`valid_from`,`valid_to`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_inventory_category_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `maintenance_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_line_id` bigint unsigned NOT NULL,
  `asset_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manufacturer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_critical` tinyint(1) DEFAULT '1',
  `status` enum('OPERATIONAL','UNDER_MAINTENANCE','FAULTY','STANDBY','DECOMMISSIONED') COLLATE utf8mb4_unicode_ci DEFAULT 'OPERATIONAL',
  `total_running_hours` decimal(10,2) DEFAULT '0.00',
  `total_cycles_count` bigint DEFAULT '0',
  `last_preventive_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `idx_asset_line` (`production_line_id`),
  KEY `idx_asset_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_assets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_category_id` bigint unsigned NOT NULL,
  `serial_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `invoice_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_start_date` date DEFAULT NULL,
  `warranty_end_date` date DEFAULT NULL,
  `status` enum('IN_STOCK','ASSIGNED','IN_USE','IN_REPAIR','LOST','RETIRED','DISPOSED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_STOCK',
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `location_id` bigint unsigned DEFAULT NULL,
  `production_line_id` bigint unsigned DEFAULT NULL,
  `responsible_user_id` bigint unsigned DEFAULT NULL,
  `maintenance_asset_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  UNIQUE KEY `uq_inventory_maintenance_asset` (`maintenance_asset_id`),
  KEY `idx_inventory_asset_category` (`inventory_category_id`),
  KEY `idx_inventory_asset_supplier` (`supplier_id`),
  KEY `idx_inventory_asset_warehouse` (`warehouse_id`),
  KEY `idx_inventory_asset_location` (`location_id`),
  KEY `idx_inventory_asset_line` (`production_line_id`),
  KEY `idx_inventory_asset_responsible_user` (`responsible_user_id`),
  KEY `idx_inventory_asset_status` (`status`),
  KEY `idx_inventory_asset_active` (`is_active`),
  CONSTRAINT `fk_inventory_asset_category` FOREIGN KEY (`inventory_category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_asset_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_asset_maintenance` FOREIGN KEY (`maintenance_asset_id`) REFERENCES `maintenance_assets` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_asset_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_asset_id` bigint unsigned NOT NULL,
  `movement_type` enum('ASSIGN','TRANSFER','RETURN','LOCATION_CHANGE','STATUS_CHANGE','MAINTENANCE_SEND','MAINTENANCE_RETURN','DISPOSAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_user_id` bigint unsigned DEFAULT NULL,
  `to_user_id` bigint unsigned DEFAULT NULL,
  `from_warehouse_id` bigint unsigned DEFAULT NULL,
  `to_warehouse_id` bigint unsigned DEFAULT NULL,
  `from_location_id` bigint unsigned DEFAULT NULL,
  `to_location_id` bigint unsigned DEFAULT NULL,
  `from_status` enum('IN_STOCK','ASSIGNED','IN_USE','IN_REPAIR','LOST','RETIRED','DISPOSED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` enum('IN_STOCK','ASSIGNED','IN_USE','IN_REPAIR','LOST','RETIRED','DISPOSED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by_user_id` bigint unsigned NOT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_movement_asset_date` (`inventory_asset_id`,`movement_date`),
  KEY `idx_inventory_movement_type` (`movement_type`),
  KEY `idx_inventory_movement_performed_by` (`performed_by_user_id`),
  KEY `idx_inventory_movement_date` (`movement_date`),
  CONSTRAINT `fk_inventory_movement_asset` FOREIGN KEY (`inventory_asset_id`) REFERENCES `inventory_assets` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recipes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_material_id` bigint unsigned DEFAULT NULL,
  `base_quantity` decimal(15,3) NOT NULL DEFAULT '1.000',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recipe_code` (`code`),
  KEY `idx_recipes_output_material` (`output_material_id`),
  KEY `idx_recipes_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `line_cycle_times` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `production_line_id` bigint unsigned NOT NULL,
  `recipe_id` bigint unsigned NOT NULL,
  `ideal_cycle_seconds` decimal(8,2) NOT NULL DEFAULT '180.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_line_recipe` (`production_line_id`,`recipe_id`),
  KEY `fk_lct_recipe` (`recipe_id`),
  CONSTRAINT `fk_lct_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lct_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `line_downtimes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `line_id` bigint unsigned NOT NULL,
  `reason_id` int unsigned NOT NULL,
  `work_order_id` bigint unsigned DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `duration_seconds` int unsigned DEFAULT NULL,
  `is_planned` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  `operator_note` text,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_line_dt_line_time` (`line_id`,`started_at`),
  KEY `idx_line_dt_status` (`status`),
  KEY `fk_line_dt_reason` (`reason_id`),
  CONSTRAINT `fk_line_dt_line` FOREIGN KEY (`line_id`) REFERENCES `production_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_line_dt_reason` FOREIGN KEY (`reason_id`) REFERENCES `downtime_reasons` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_turkish_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_warehouse_code` (`warehouse_id`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `maintenance_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint unsigned NOT NULL,
  `plan_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trigger_type` enum('TIME_BASED','HOURS_BASED','CYCLES_BASED') COLLATE utf8mb4_unicode_ci DEFAULT 'HOURS_BASED',
  `interval_days` int DEFAULT NULL,
  `interval_hours` decimal(10,2) DEFAULT NULL,
  `interval_cycles` bigint DEFAULT NULL,
  `last_executed_at` datetime DEFAULT NULL,
  `last_executed_hours` decimal(10,2) DEFAULT '0.00',
  `last_executed_cycles` bigint DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_code` (`plan_code`),
  KEY `idx_plan_asset` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `maintenance_spare_parts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `maintenance_work_order_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity_used` decimal(12,3) NOT NULL,
  `unit_cost_snapshot` decimal(15,4) NOT NULL,
  `total_cost_snapshot` decimal(15,4) NOT NULL,
  `stock_movement_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msp_mwo` (`maintenance_work_order_id`),
  KEY `idx_msp_mat` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `maintenance_work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_id` bigint unsigned NOT NULL,
  `line_downtime_id` bigint unsigned DEFAULT NULL,
  `maintenance_type` enum('PREVENTIVE','CORRECTIVE','EMERGENCY','CALIBRATION') COLLATE utf8mb4_unicode_ci DEFAULT 'CORRECTIVE',
  `priority` enum('LOW','MEDIUM','HIGH','CRITICAL') COLLATE utf8mb4_unicode_ci DEFAULT 'HIGH',
  `status` enum('OPEN','ASSIGNED','IN_PROGRESS','WAITING_PART','COMPLETED','VERIFIED','CLOSED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'OPEN',
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `reported_by_user_id` bigint unsigned NOT NULL,
  `failure_category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'MEKANIK',
  `root_cause_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_description` text COLLATE utf8mb4_unicode_ci,
  `action_taken` text COLLATE utf8mb4_unicode_ci,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by_user_id` bigint unsigned DEFAULT NULL,
  `downtime_minutes` decimal(10,2) DEFAULT '0.00',
  `labor_hours` decimal(6,2) DEFAULT '0.00',
  `labor_cost` decimal(12,4) DEFAULT '0.0000',
  `spare_parts_cost` decimal(12,4) DEFAULT '0.0000',
  `total_maintenance_cost` decimal(12,4) DEFAULT '0.0000',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_no` (`work_order_no`),
  KEY `idx_mwo_asset` (`asset_id`),
  KEY `idx_mwo_status` (`status`),
  KEY `idx_mwo_dt` (`line_downtime_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `material_suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `supplier_material_code` varchar(100) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `lead_time_days` int unsigned DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_material_supplier` (`material_id`,`supplier_id`),
  KEY `fk_material_suppliers_supplier` (`supplier_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_turkish_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_turkish_ci NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `unit_id` bigint unsigned NOT NULL,
  `min_stock` decimal(15,3) NOT NULL DEFAULT '0.000',
  `max_stock` decimal(15,3) DEFAULT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TL',
  `description` text COLLATE utf8mb4_turkish_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `fk_materials_category` (`category_id`),
  KEY `fk_materials_unit` (`unit_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `mes_event_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mes_work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_material_id` bigint unsigned NOT NULL,
  `recipe_id` bigint unsigned NOT NULL,
  `production_line_id` bigint unsigned NOT NULL,
  `planned_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `produced_quantity` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('PLANNED','READY','RUNNING','PAUSED','FAILED','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PLANNED',
  `planned_start_at` datetime DEFAULT NULL,
  `planned_end_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_no` (`work_order_no`),
  KEY `idx_wo_status` (`status`),
  KEY `idx_wo_material` (`product_material_id`),
  KEY `idx_wo_recipe` (`recipe_id`),
  KEY `idx_wo_line` (`production_line_id`),
  CONSTRAINT `fk_mes_wo_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mes_wo_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mes_production_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `product_material_id` bigint unsigned NOT NULL,
  `production_line_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unit_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `cost_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TL',
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PANEL_COMPLETED',
  `event_time` datetime NOT NULL,
  `source` enum('SIMULATOR','MES','PLC','MQTT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SIMULATOR',
  `status` enum('PENDING','PROCESSED','FAILED','IGNORED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `stock_movement_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`),
  KEY `idx_event_wo` (`work_order_id`),
  KEY `idx_event_material` (`product_material_id`),
  KEY `idx_event_line` (`production_line_id`),
  KEY `idx_event_status` (`status`),
  KEY `idx_event_time` (`event_time`),
  KEY `idx_mes_prod_events_stock_ref` (`stock_movement_ref`),
  CONSTRAINT `fk_mes_event_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_mes_event_wo` FOREIGN KEY (`work_order_id`) REFERENCES `mes_work_orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mes_simulations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `interval_seconds` int NOT NULL DEFAULT '180',
  `paused_remaining_seconds` int DEFAULT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `last_event_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'IDLE',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `total_simulated_panels` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `retry_count` int unsigned NOT NULL DEFAULT '0',
  `last_error_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_retry_at` datetime DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_order_id` (`work_order_id`),
  KEY `idx_sim_active` (`is_active`),
  KEY `idx_sim_next_run` (`next_run_at`),
  CONSTRAINT `fk_mes_sim_wo` FOREIGN KEY (`work_order_id`) REFERENCES `mes_work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `oee_shift_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shift_id` tinyint unsigned NOT NULL,
  `production_line_id` bigint unsigned NOT NULL,
  `business_date` date NOT NULL,
  `availability` decimal(6,2) NOT NULL DEFAULT '0.00',
  `performance` decimal(6,2) NOT NULL DEFAULT '0.00',
  `quality` decimal(6,2) NOT NULL DEFAULT '0.00',
  `oee` decimal(6,2) NOT NULL DEFAULT '0.00',
  `production_quantity` int unsigned NOT NULL DEFAULT '0',
  `downtime_minutes` int unsigned NOT NULL DEFAULT '0',
  `planned_downtime_minutes` int unsigned NOT NULL DEFAULT '0',
  `snapshot_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_line_shift_date` (`production_line_id`,`shift_id`,`business_date`),
  KEY `fk_oss_shift` (`shift_id`),
  CONSTRAINT `fk_oss_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oss_shift` FOREIGN KEY (`shift_id`) REFERENCES `energy_shifts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `panel_units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_no` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `production_event_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `production_line_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `stock_movement_id` bigint unsigned DEFAULT NULL,
  `stock_movement_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('PRODUCED','IN_STOCK','QUALITY_PENDING','QUALITY_APPROVED','QUALITY_REJECTED','QUARANTINE','SHIPPED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_STOCK',
  `unit_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TL',
  `quality_notes` text COLLATE utf8mb4_unicode_ci,
  `produced_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_no` (`serial_no`),
  KEY `idx_pu_material` (`material_id`),
  KEY `idx_pu_work_order` (`work_order_id`),
  KEY `idx_pu_serial` (`serial_no`),
  KEY `idx_pu_status` (`status`),
  KEY `idx_pu_warehouse` (`warehouse_id`),
  KEY `idx_pu_location` (`location_id`),
  KEY `idx_pu_produced_at` (`produced_at`),
  KEY `idx_pu_movement_ref` (`stock_movement_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `purchase_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `priority` enum('LOW','MEDIUM','HIGH','URGENT') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MEDIUM',
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED','ORDERED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `description` text COLLATE utf8mb4_unicode_ci,
  `approval_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pr_request_no` (`request_no`),
  KEY `idx_pr_requested_by` (`requested_by`),
  KEY `idx_pr_department` (`department_id`),
  KEY `idx_pr_request_date` (`request_date`),
  KEY `idx_pr_required_date` (`required_date`),
  KEY `idx_pr_priority` (`priority`),
  KEY `idx_pr_status` (`status`),
  KEY `idx_pr_status_date` (`status`,`request_date`),
  KEY `idx_pr_approved_by` (`approved_by`),
  CONSTRAINT `fk_pr_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `ordered_by` bigint unsigned NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `status` enum('DRAFT','SENT','CONFIRMED','PARTIALLY_RECEIVED','RECEIVED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `subtotal` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '20.00',
  `tax_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_po_purchase_request_id` (`purchase_request_id`),
  KEY `idx_po_supplier_id` (`supplier_id`),
  KEY `idx_po_ordered_by` (`ordered_by`),
  KEY `idx_po_order_date` (`order_date`),
  KEY `idx_po_expected_delivery_date` (`expected_delivery_date`),
  KEY `idx_po_status` (`status`),
  CONSTRAINT `fk_po_purchase_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_request_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `requested_quantity` decimal(15,3) NOT NULL,
  `estimated_unit_price` decimal(15,4) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'TL',
  `suggested_supplier_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pri_request_id` (`purchase_request_id`),
  KEY `idx_pri_material_id` (`material_id`),
  KEY `idx_pri_supplier_id` (`suggested_supplier_id`),
  CONSTRAINT `fk_pri_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_pri_quantity` CHECK ((`requested_quantity` > 0)),
  CONSTRAINT `chk_pri_unit_price` CHECK (((`estimated_unit_price` is null) or (`estimated_unit_price` >= 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `purchase_request_item_id` bigint unsigned DEFAULT NULL,
  `material_id` bigint unsigned NOT NULL,
  `ordered_quantity` decimal(15,3) NOT NULL,
  `received_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `unit_price` decimal(15,4) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TRY',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '20.00',
  `line_total` decimal(15,4) NOT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poi_purchase_order_id` (`purchase_order_id`),
  KEY `idx_poi_purchase_request_item_id` (`purchase_request_item_id`),
  KEY `idx_poi_material_id` (`material_id`),
  CONSTRAINT `fk_poi_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_poi_purchase_request_item` FOREIGN KEY (`purchase_request_item_id`) REFERENCES `purchase_request_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_poi_ordered_quantity` CHECK ((`ordered_quantity` > 0)),
  CONSTRAINT `chk_poi_received_le_ordered` CHECK ((`received_quantity` <= `ordered_quantity`)),
  CONSTRAINT `chk_poi_received_quantity` CHECK ((`received_quantity` >= 0)),
  CONSTRAINT `chk_poi_tax_rate_max` CHECK ((`tax_rate` <= 100)),
  CONSTRAINT `chk_poi_tax_rate_min` CHECK ((`tax_rate` >= 0)),
  CONSTRAINT `chk_poi_unit_price` CHECK ((`unit_price` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `received_by` bigint unsigned NOT NULL,
  `receipt_date` date NOT NULL,
  `delivery_note_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_document_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('DRAFT','COMPLETED','CANCELLED') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'COMPLETED',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `idx_prec_purchase_order_id` (`purchase_order_id`),
  KEY `idx_prec_warehouse_id` (`warehouse_id`),
  KEY `idx_prec_location_id` (`location_id`),
  KEY `idx_prec_received_by` (`received_by`),
  KEY `idx_prec_receipt_date` (`receipt_date`),
  KEY `idx_prec_status` (`status`),
  KEY `idx_prec_delivery_note_no` (`delivery_note_no`),
  CONSTRAINT `fk_prec_purchase_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_receipt_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_receipt_id` bigint unsigned NOT NULL,
  `purchase_order_item_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `received_quantity` decimal(15,3) NOT NULL,
  `accepted_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `rejected_quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `stock_movement_id` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_preci_receipt_id` (`purchase_receipt_id`),
  KEY `idx_preci_po_item_id` (`purchase_order_item_id`),
  KEY `idx_preci_material_id` (`material_id`),
  KEY `idx_preci_stock_movement_id` (`stock_movement_id`),
  CONSTRAINT `fk_preci_po_item` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_preci_receipt` FOREIGN KEY (`purchase_receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_preci_accepted_quantity` CHECK ((`accepted_quantity` >= 0)),
  CONSTRAINT `chk_preci_accepted_rejected_sum` CHECK (((`accepted_quantity` + `rejected_quantity`) <= `received_quantity`)),
  CONSTRAINT `chk_preci_received_quantity` CHECK ((`received_quantity` > 0)),
  CONSTRAINT `chk_preci_rejected_quantity` CHECK ((`rejected_quantity` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recipe_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recipe_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,4) NOT NULL,
  `scrap_rate_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `is_critical` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recipe_material` (`recipe_id`,`material_id`),
  KEY `idx_recipe_items_recipe` (`recipe_id`),
  KEY `idx_recipe_items_material` (`material_id`),
  CONSTRAINT `fk_recipe_items_recipe` FOREIGN KEY (`recipe_id`) REFERENCES `recipes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role_id`,`permission_id`),
  KEY `fk_role_permissions_permission` (`permission_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_turkish_ci NOT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `shipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('DRAFT','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shipment_no` (`shipment_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shipment_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint unsigned NOT NULL,
  `panel_unit_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipment_panel` (`shipment_id`,`panel_unit_id`),
  KEY `panel_unit_id` (`panel_unit_id`),
  CONSTRAINT `shipment_items_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shipment_items_ibfk_2` FOREIGN KEY (`panel_unit_id`) REFERENCES `panel_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT '0.000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_balance` (`material_id`,`location_id`),
  KEY `fk_stock_balances_location` (`location_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint unsigned NOT NULL,
  `location_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `movement_type` varchar(30) COLLATE utf8mb4_turkish_ci NOT NULL,
  `quantity` decimal(15,3) NOT NULL,
  `unit_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TL',
  `reference_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_stock_movements_material` (`material_id`),
  KEY `fk_stock_movements_location` (`location_id`),
  KEY `fk_stock_movements_user` (`user_id`),
  KEY `idx_sm_reference_no` (`reference_no`),
  CONSTRAINT `chk_stock_movement_quantity` CHECK ((`quantity` > 0))
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_turkish_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_turkish_ci NOT NULL,
  `contact_name` varchar(100) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_turkish_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_turkish_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `units` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_turkish_ci NOT NULL,
  `symbol` varchar(10) COLLATE utf8mb4_turkish_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_turkish_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_turkish_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_turkish_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_turkish_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_turkish_ci NOT NULL,
  `description` text COLLATE utf8mb4_turkish_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET FOREIGN_KEY_CHECKS = 1;
