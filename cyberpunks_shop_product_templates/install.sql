-- Cyberpunks Shop Product Templates — database (run once).
-- Replace `oc_` with your table prefix from config.php (DB_PREFIX).

ALTER TABLE `oc_product` ADD `cyberpunks_template` VARCHAR(255) NOT NULL DEFAULT '';
