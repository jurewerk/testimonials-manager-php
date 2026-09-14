-- ---------------------------------------------------------------------------
-- Testimonials Manager — seed.sql
--
-- Demo vsebina, da aplikacijo lahko takoj poženete z zgledi.
-- Uvozite po datoteki schema.sql:
--
--   mysql -u root -p testimonials < seed.sql
--
-- Vsebuje:
--   * 1 skrbnika    — admin@example.test / local-demo-only  (samo za lokalni demo)
--   * 3 izdelke     — izmišljeni demo izdelki (predpona "demo-"); pravi
--                     katalog se uvozi s `php bin/sync.php`, ne od tod
--   * 24 landingov  — po en angleški master in sedem lokaliziranih držav na
--                     izdelek, z vsebino v cirilici, grščini in s šumniki
--   * 18 mnenj      — aktivna in neaktivna, fiksne in naključne ocene, vsi
--                     trije spoli, urejena po vrstnem redu, nekatera s
--                     povezavo; večina držav je namenoma praznih, da se vidi
--                     dedovanje angleškega nabora
--   * 12 slik       — WebP izvirniki in ustvarjene pomanjšave
--   * zapise v dnevniku sprememb za demo mnenja
--
-- Zapisi slik se sklicujejo na datoteke v storage/uploads/. Te datoteke so
-- priložene projektu; glejte README.md.
--
-- Geslo skrbnika je shranjeno kot zgoščena vrednost iz password_hash().
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `created_at`, `updated_at`) VALUES (1,'Demo Administrator','admin@example.test','$2y$12$.DLMkBe0tDb7tTuznB2HvObohwEW9JEGIZA.kop1pCtoIQDw3xzrS','2026-09-14 08:14:46','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `parent_sku`, `created_at`, `updated_at`) VALUES (1,'AURORA-01','2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `products` (`id`, `parent_sku`, `created_at`, `updated_at`) VALUES (2,'TRAIL-02','2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `products` (`id`, `parent_sku`, `created_at`, `updated_at`) VALUES (3,'BLOOM-03','2026-09-14 08:14:46','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `landings` WRITE;
/*!40000 ALTER TABLE `landings` DISABLE KEYS */;
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (1,'demo-AURORA-01-EN',1,'EN',1,'Aurora ambient lamp','A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.','https://example.com/en/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (2,'demo-AURORA-01-SI',1,'SI',0,'Aurora ambient lamp','A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.','https://example.com/si/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (3,'demo-AURORA-01-IT',1,'IT',0,'Aurora ambient lamp','A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.','https://example.com/it/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (4,'demo-AURORA-01-DE',1,'DE',0,'Aurora ambient lamp','A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.','https://example.com/de/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (5,'demo-AURORA-01-FR',1,'FR',0,'Aurora ambient lamp','A softer light for everyday spaces. Rechargeable, portable, and made for slow evenings.','https://example.com/fr/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (6,'demo-TRAIL-02-EN',2,'EN',1,'Trail everyday backpack','Room for every adventure. Lightweight construction with thoughtful everyday organization.','https://example.com/en/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (7,'demo-TRAIL-02-SI',2,'SI',0,'Trail everyday backpack','Room for every adventure. Lightweight construction with thoughtful everyday organization.','https://example.com/si/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (8,'demo-TRAIL-02-IT',2,'IT',0,'Trail everyday backpack','Room for every adventure. Lightweight construction with thoughtful everyday organization.','https://example.com/it/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (9,'demo-TRAIL-02-DE',2,'DE',0,'Trail everyday backpack','Room for every adventure. Lightweight construction with thoughtful everyday organization.','https://example.com/de/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (10,'demo-TRAIL-02-FR',2,'FR',0,'Trail everyday backpack','Room for every adventure. Lightweight construction with thoughtful everyday organization.','https://example.com/fr/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (11,'demo-BLOOM-03-EN',3,'EN',1,'Bloom self-watering planter','A little more green, a little less effort. Keep your favorite plants thriving.','https://example.com/en/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (12,'demo-BLOOM-03-SI',3,'SI',0,'Bloom self-watering planter','A little more green, a little less effort. Keep your favorite plants thriving.','https://example.com/si/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (13,'demo-BLOOM-03-IT',3,'IT',0,'Bloom self-watering planter','A little more green, a little less effort. Keep your favorite plants thriving.','https://example.com/it/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (14,'demo-BLOOM-03-DE',3,'DE',0,'Bloom self-watering planter','A little more green, a little less effort. Keep your favorite plants thriving.','https://example.com/de/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (15,'demo-BLOOM-03-FR',3,'FR',0,'Bloom self-watering planter','A little more green, a little less effort. Keep your favorite plants thriving.','https://example.com/fr/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (16,'demo-AURORA-01-BG',1,'BG',0,'Аурора – лампа за уют','По-мека светлина за всекидневните пространства. Презареждаема, преносима и създадена за спокойни вечери.','https://example.com/bg/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (17,'demo-AURORA-01-GR',1,'GR',0,'Aurora – λάμπα ατμόσφαιρας','Πιο απαλό φως για τους καθημερινούς χώρους. Επαναφορτιζόμενη, φορητή και φτιαγμένη για ήρεμα βράδια.','https://example.com/gr/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (18,'demo-AURORA-01-CZ',1,'CZ',0,'Aurora – náladová lampa','Měkčí světlo pro každodenní prostory. Dobíjecí, přenosná a stvořená pro klidné večery.','https://example.com/cz/AURORA-01',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (19,'demo-TRAIL-02-BG',2,'BG',0,'Trail – раница за всеки ден','Място за всяко приключение. Лека конструкция с добре обмислена подредба за всекидневието.','https://example.com/bg/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (20,'demo-TRAIL-02-GR',2,'GR',0,'Trail – καθημερινό σακίδιο','Χώρος για κάθε περιπέτεια. Ελαφριά κατασκευή με προσεγμένη καθημερινή οργάνωση.','https://example.com/gr/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (21,'demo-TRAIL-02-CZ',2,'CZ',0,'Trail – batoh na každý den','Místo pro každé dobrodružství. Lehká konstrukce s promyšleným uspořádáním pro běžný den.','https://example.com/cz/TRAIL-02',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (22,'demo-BLOOM-03-BG',3,'BG',0,'Bloom – саксия със самополиване','Малко повече зеленина, малко по-малко усилия. Любимите ви растения остават свежи.','https://example.com/bg/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (23,'demo-BLOOM-03-GR',3,'GR',0,'Bloom – γλάστρα με αυτόματο πότισμα','Λίγο περισσότερο πράσινο, λίγο λιγότερος κόπος. Κρατήστε τα αγαπημένα σας φυτά ζωντανά.','https://example.com/gr/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `landings` (`id`, `external_id`, `product_id`, `country_code`, `is_master`, `title`, `description`, `landing_url`, `product_image_url`, `last_synced_at`, `version`, `created_at`, `updated_at`) VALUES (24,'demo-BLOOM-03-CZ',3,'CZ',0,'Bloom – samozavlažovací květináč','Trochu více zeleně, trochu méně starostí. Vaše oblíbené rostliny zůstanou v kondici.','https://example.com/cz/BLOOM-03',NULL,'2026-09-14 08:14:46',0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `landings` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,1,'Emma Wilson','Beautifully made and even better in person. It has become part of my daily routine.','https://example.com/en/AURORA-01','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (2,1,'Alex Morgan','Exactly what I was looking for. Thoughtful details and excellent quality.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (3,1,'James Parker','Arrived quickly and works well. A lovely addition to our home.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (4,3,'Emma Wilson','Un prodotto bellissimo, lo uso ogni giorno.','https://example.com/it/AURORA-01','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (5,3,'Alex Morgan','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (6,3,'James Parker','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (7,6,'Emma Wilson','Beautifully made and even better in person. It has become part of my daily routine.','https://example.com/en/TRAIL-02','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (8,6,'Alex Morgan','Exactly what I was looking for. Thoughtful details and excellent quality.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (9,6,'James Parker','Arrived quickly and works well. A lovely addition to our home.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (10,8,'Emma Wilson','Un prodotto bellissimo, lo uso ogni giorno.','https://example.com/it/TRAIL-02','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (11,8,'Alex Morgan','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (12,8,'James Parker','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (13,11,'Emma Wilson','Beautifully made and even better in person. It has become part of my daily routine.','https://example.com/en/BLOOM-03','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (14,11,'Alex Morgan','Exactly what I was looking for. Thoughtful details and excellent quality.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (15,11,'James Parker','Arrived quickly and works well. A lovely addition to our home.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (16,13,'Emma Wilson','Un prodotto bellissimo, lo uso ogni giorno.','https://example.com/it/BLOOM-03','fixed',5,'female',1,0,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (17,13,'Alex Morgan','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'random',NULL,'unisex',1,1,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonials` (`id`, `landing_id`, `author_name`, `comment`, `link`, `rating_mode`, `rating`, `gender`, `is_active`, `sort_order`, `version`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (18,13,'James Parker','Un prodotto bellissimo, lo uso ogni giorno.',NULL,'fixed',4,'male',0,2,0,1,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `testimonial_images` WRITE;
/*!40000 ALTER TABLE `testimonial_images` DISABLE KEYS */;
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (1,1,'6a32ea6e-b36f-4c3b-8fcb-11133e41bdf3.webp','6a32ea6e-b36f-4c3b-8fcb-11133e41bdf3-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (2,1,'f01c8bed-11e4-41b6-8038-45cf57add341.webp','f01c8bed-11e4-41b6-8038-45cf57add341-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (3,4,'47dcb75e-53b3-425f-a16a-171b8644241a.webp','47dcb75e-53b3-425f-a16a-171b8644241a-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (4,4,'b0977db5-a93f-4fe3-9caa-3572a765ae43.webp','b0977db5-a93f-4fe3-9caa-3572a765ae43-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (5,7,'d161a88c-38c1-4c8c-a02a-4036078b3fc3.webp','d161a88c-38c1-4c8c-a02a-4036078b3fc3-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (6,7,'7da782a3-a812-41bc-a2e9-15fb26cf6beb.webp','7da782a3-a812-41bc-a2e9-15fb26cf6beb-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (7,10,'c3f23ecc-9429-4117-81c3-91363b22864f.webp','c3f23ecc-9429-4117-81c3-91363b22864f-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (8,10,'f7bb9314-261f-4703-aa73-617a9277868d.webp','f7bb9314-261f-4703-aa73-617a9277868d-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (9,13,'868c2344-320e-4c18-a1ed-0f8d9cfa27c2.webp','868c2344-320e-4c18-a1ed-0f8d9cfa27c2-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (10,13,'a02df00b-988a-4747-86c9-d0e39aa95eeb.webp','a02df00b-988a-4747-86c9-d0e39aa95eeb-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (11,16,'15a6333e-b932-450e-9dab-b9958aa9e645.webp','15a6333e-b932-450e-9dab-b9958aa9e645-thumb.webp','demo-0.png','image/webp',3142,640,480,0,'2026-09-14 08:14:46','2026-09-14 08:14:46');
INSERT INTO `testimonial_images` (`id`, `testimonial_id`, `path`, `thumbnail_path`, `original_filename`, `mime_type`, `size`, `width`, `height`, `sort_order`, `created_at`, `updated_at`) VALUES (12,16,'bafbab96-8237-4ba5-b90c-27de334ec886.webp','bafbab96-8237-4ba5-b90c-27de334ec886-thumb.webp','demo-1.png','image/webp',3346,640,480,1,'2026-09-14 08:14:46','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `testimonial_images` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (1,1,'created','testimonial',1,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (2,1,'created','testimonial',2,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (3,1,'created','testimonial',3,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (4,1,'created','testimonial',4,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (5,1,'created','testimonial',5,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (6,1,'created','testimonial',6,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (7,1,'created','testimonial',7,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (8,1,'created','testimonial',8,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (9,1,'created','testimonial',9,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (10,1,'created','testimonial',10,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (11,1,'created','testimonial',11,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (12,1,'created','testimonial',12,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (13,1,'created','testimonial',13,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (14,1,'created','testimonial',14,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (15,1,'created','testimonial',15,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (16,1,'created','testimonial',16,NULL,'{\"author_name\": \"Emma Wilson\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (17,1,'created','testimonial',17,NULL,'{\"author_name\": \"Alex Morgan\"}','2026-09-14 08:14:46');
INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES (18,1,'created','testimonial',18,NULL,'{\"author_name\": \"James Parker\"}','2026-09-14 08:14:46');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS = 1;
