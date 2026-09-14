-- ---------------------------------------------------------------------------
-- Testimonials Manager — schema.sql
--
-- Cilj: MySQL 8.0+ / MariaDB 10.4+, utf8mb4 / utf8mb4_unicode_ci.
--
-- Ustvarjanje baze in uvoz:
--
--   CREATE DATABASE testimonials CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   mysql -u root -p testimonials < schema.sql
--   mysql -u root -p testimonials < seed.sql
--
-- Ponoven zagon te datoteke odstrani in znova ustvari vse tabele.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `testimonial_images`;
DROP TABLE IF EXISTS `testimonials`;
DROP TABLE IF EXISTS `landings`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- Uporabniki. Gesla so shranjena kot zgoščene vrednosti iz password_hash().
CREATE TABLE `users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255) NOT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Krovni SKU. Besedilo izdelka ni podvojeno: bere se z angleškega landinga.
CREATE TABLE `products` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_sku` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_parent_sku_unique` (`parent_sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Landingi, uvoženi iz GET /landings.
--
-- `external_id` je ponudnikov stabilen identifikator in edini ključ, po katerem
-- teče sinhronizacija (upsert). Lokalni `id` se zato nikoli ne spremeni in
-- mnenja ne izgubijo reference. Par (product_id, country_code) je enakovreden
-- in je tu zaradi dodatne zaščite pred podvojitvijo.
CREATE TABLE `landings` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_id`       VARCHAR(100) NOT NULL,
  `product_id`        BIGINT UNSIGNED NOT NULL,
  `country_code`      VARCHAR(8) NOT NULL,
  `is_master`         TINYINT(1) NOT NULL DEFAULT 0,
  `title`             VARCHAR(255) NOT NULL,
  `description`       TEXT NULL,
  `landing_url`       VARCHAR(2048) NOT NULL,
  `product_image_url` VARCHAR(2048) NULL,
  `last_synced_at`    TIMESTAMP NULL DEFAULT NULL,
  `version`           INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `landings_external_id_unique` (`external_id`),
  UNIQUE KEY `landings_product_country_unique` (`product_id`, `country_code`),
  KEY `landings_product_master_index` (`product_id`, `is_master`),
  CONSTRAINT `landings_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mnenja. `rating_mode` = 'random' pomeni, da je `rating` NULL in se ocena
-- določi ob prikazu (4 ali 5), da povprečje ostane realno.
CREATE TABLE `testimonials` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `landing_id`  BIGINT UNSIGNED NOT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `comment`     TEXT NOT NULL,
  `link`        VARCHAR(2048) NULL,
  `rating_mode` ENUM('fixed','random') NOT NULL DEFAULT 'fixed',
  `rating`      TINYINT UNSIGNED NULL,
  `gender`      ENUM('male','female','unisex') NOT NULL DEFAULT 'unisex',
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `version`     INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`  BIGINT UNSIGNED NULL,
  `updated_by`  BIGINT UNSIGNED NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `testimonials_landing_order_index` (`landing_id`, `sort_order`, `id`),
  KEY `testimonials_landing_active_index` (`landing_id`, `is_active`),
  CONSTRAINT `testimonials_landing_id_foreign` FOREIGN KEY (`landing_id`) REFERENCES `landings` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `testimonials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `testimonials_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `testimonials_rating_check` CHECK (
    (`rating_mode` = 'random' AND `rating` IS NULL) OR
    (`rating_mode` = 'fixed'  AND `rating` BETWEEN 1 AND 5)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slike mnenj. Datoteke živijo zunaj baze in zunaj korena dokumenta;
-- ime datoteke je generiran UUID, izvirno ime je le metapodatek.
CREATE TABLE `testimonial_images` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `testimonial_id`    BIGINT UNSIGNED NOT NULL,
  `path`              VARCHAR(255) NOT NULL,
  `thumbnail_path`    VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `mime_type`         VARCHAR(100) NOT NULL,
  `size`              INT UNSIGNED NOT NULL,
  `width`             INT UNSIGNED NOT NULL,
  `height`            INT UNSIGNED NOT NULL,
  `sort_order`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `testimonial_images_order_index` (`testimonial_id`, `sort_order`),
  CONSTRAINT `testimonial_images_testimonial_id_foreign` FOREIGN KEY (`testimonial_id`) REFERENCES `testimonials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dnevnik sprememb: kdo je kdaj kaj spremenil, s starimi in novimi vrednostmi.
CREATE TABLE `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `action`      VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id`   BIGINT UNSIGNED NOT NULL,
  `old_values`  JSON NULL,
  `new_values`  JSON NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_entity_index` (`entity_type`, `entity_id`, `id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
