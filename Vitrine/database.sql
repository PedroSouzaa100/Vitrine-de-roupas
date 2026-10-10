-- =====================================================================
--  VITRINE - Plataforma de vitrine virtual para lojas de roupas
--  Banco de dados MySQL 5.7+ / 8.0
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `vitrine`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vitrine`;

-- ---------------------------------------------------------------------
-- USERS - todos os usuários da plataforma (cliente, vendedor, admin)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(160) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('customer','seller','admin') NOT NULL DEFAULT 'customer',
  `status`     ENUM('active','blocked') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- SELLERS - perfil comercial vinculado a um usuário (role = seller)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sellers`;
CREATE TABLE `sellers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `document`         VARCHAR(24) DEFAULT NULL COMMENT 'CPF ou CNPJ (opcional)',
  `status`           ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  `rejection_reason` TEXT DEFAULT NULL,
  `reviewed_by`      INT UNSIGNED DEFAULT NULL,
  `reviewed_at`      DATETIME DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sellers_user` (`user_id`),
  KEY `idx_sellers_status` (`status`),
  CONSTRAINT `fk_sellers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sellers_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CATEGORIES - categorias e gêneros (type = gender | category)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `type`       ENUM('gender','category') NOT NULL DEFAULT 'category',
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `icon`       VARCHAR(60) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categories_slug` (`slug`),
  KEY `idx_categories_type` (`type`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STORES - lojas dos vendedores
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `seller_id`    INT UNSIGNED NOT NULL,
  `category_id`  INT UNSIGNED DEFAULT NULL COMMENT 'Gênero principal da loja',
  `name`         VARCHAR(140) NOT NULL COMMENT 'Nome da loja (razão)',
  `display_name` VARCHAR(140) DEFAULT NULL COMMENT 'Nome fantasia',
  `slug`         VARCHAR(160) NOT NULL,
  `description`  TEXT DEFAULT NULL,
  `logo`         VARCHAR(255) DEFAULT NULL,
  `banner`       VARCHAR(255) DEFAULT NULL,
  `instagram`    VARCHAR(120) DEFAULT NULL,
  `whatsapp`     VARCHAR(30)  DEFAULT NULL,
  `website`      VARCHAR(180) DEFAULT NULL,
  `city`         VARCHAR(90)  DEFAULT NULL,
  `state`        CHAR(2)      DEFAULT NULL,
  `address`      VARCHAR(200) DEFAULT NULL,
  `views`        INT UNSIGNED NOT NULL DEFAULT 0,
  `verified`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_featured`  TINYINT(1) NOT NULL DEFAULT 0,
  `active`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_stores_slug` (`slug`),
  UNIQUE KEY `uniq_stores_seller` (`seller_id`),
  KEY `idx_stores_active` (`active`),
  KEY `idx_stores_featured` (`is_featured`),
  FULLTEXT KEY `ft_stores` (`name`,`display_name`,`description`),
  CONSTRAINT `fk_stores_seller` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stores_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PRODUCTS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`       INT UNSIGNED NOT NULL,
  `category_id`    INT UNSIGNED DEFAULT NULL,
  `subcategory_id` INT UNSIGNED DEFAULT NULL,
  `name`           VARCHAR(180) NOT NULL,
  `slug`           VARCHAR(200) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `brand`          VARCHAR(120) DEFAULT NULL,
  `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `promo_price`    DECIMAL(10,2) DEFAULT NULL,
  `stock`          INT NOT NULL DEFAULT 0,
  `sku`            VARCHAR(60) DEFAULT NULL,
  `sizes`          VARCHAR(180) DEFAULT NULL COMMENT 'Ex: P,M,G,GG',
  `colors`         VARCHAR(180) DEFAULT NULL COMMENT 'Ex: Preto,Branco',
  `main_image`     VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `featured`       TINYINT(1) NOT NULL DEFAULT 0,
  `views`          INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_products_slug` (`slug`),
  KEY `idx_products_store` (`store_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_price` (`price`),
  KEY `idx_products_featured` (`featured`),
  FULLTEXT KEY `ft_products` (`name`,`description`,`brand`),
  CONSTRAINT `fk_products_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PRODUCT_IMAGES - galeria adicional
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pimg_product` (`product_id`),
  CONSTRAINT `fk_pimg_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- FAVORITES - produtos favoritados
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_fav` (`user_id`,`product_id`),
  KEY `idx_fav_product` (`product_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STORE_FAVORITES - lojas favoritadas
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `store_favorites`;
CREATE TABLE `store_favorites` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `store_id`   INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sfav` (`user_id`,`store_id`),
  KEY `idx_sfav_store` (`store_id`),
  CONSTRAINT `fk_sfav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sfav_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PRODUCT_VIEWS - histórico de visualizações
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `product_views`;
CREATE TABLE `product_views` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pv_product` (`product_id`),
  KEY `idx_pv_created` (`created_at`),
  CONSTRAINT `fk_pv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pv_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- REPORTS - denúncias de produtos/lojas
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` INT UNSIGNED DEFAULT NULL,
  `store_id`    INT UNSIGNED DEFAULT NULL,
  `product_id`  INT UNSIGNED DEFAULT NULL,
  `reason`      VARCHAR(120) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status`      ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_status` (`status`),
  CONSTRAINT `fk_rep_user` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rep_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ADMIN_LOGS - histórico de atividades administrativas
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_logs`;
CREATE TABLE `admin_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(120) NOT NULL,
  `target_type` VARCHAR(40) DEFAULT NULL,
  `target_id`   INT UNSIGNED DEFAULT NULL,
  `details`     TEXT DEFAULT NULL,
  `ip_address`  VARCHAR(45) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_admin` (`admin_id`),
  KEY `idx_logs_created` (`created_at`),
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PASSWORD_RESETS
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(160) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DADOS INICIAIS
-- =====================================================================

INSERT INTO `categories` (`name`,`slug`,`type`,`sort_order`) VALUES
('Masculino','masculino','gender',1),
('Feminino','feminino','gender',2),
('Unissex','unissex','gender',3);

INSERT INTO `categories` (`name`,`slug`,`type`,`sort_order`) VALUES
('Camisas','camisas','category',1),
('Camisetas','camisetas','category',2),
('Calças','calcas','category',3),
('Shorts','shorts','category',4),
('Vestidos','vestidos','category',5),
('Saias','saias','category',6),
('Jaquetas','jaquetas','category',7),
('Moletons','moletons','category',8),
('Acessórios','acessorios','category',9),
('Calçados','calcados','category',10),
('Outros','outros','category',11);

SET FOREIGN_KEY_CHECKS = 1;