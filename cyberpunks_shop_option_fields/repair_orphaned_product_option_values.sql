-- Prefer Catalog → Options → «Обновить в товарах» (OCMOD 1.5.4+) after saving the option.
-- This SQL is an optional one-off if you need a manual DB repair instead.
--
-- Repair orphaned product_option_value rows after Catalog → Options palette resave
-- (old option_value_id no longer exists → storefront shows empty selects / no swatches).
--
-- Usage:
--   docker exec -i cyberpunks-oc-db mysql -ushop -plocaldev_shop shop < repair_orphaned_product_option_values.sql
--
-- Optional: set @product_id to limit to one product (NULL = all products).

SET @product_id = 54;

-- Remove broken POV rows (option_value missing)
DELETE pov
FROM oc_product_option_value pov
LEFT JOIN oc_option_value ov ON pov.option_value_id = ov.option_value_id
WHERE ov.option_value_id IS NULL
  AND (@product_id IS NULL OR pov.product_id = @product_id);

-- Re-attach all catalog option values for product options that currently have none
INSERT INTO oc_product_option_value (
  product_option_id, product_id, option_id, option_value_id,
  quantity, subtract, price, price_prefix, points, points_prefix, weight, weight_prefix
)
SELECT
  po.product_option_id,
  po.product_id,
  po.option_id,
  ov.option_value_id,
  1 AS quantity,
  0 AS subtract,
  0 AS price,
  '+' AS price_prefix,
  0 AS points,
  '+' AS points_prefix,
  0 AS weight,
  '+' AS weight_prefix
FROM oc_product_option po
INNER JOIN oc_option_value ov ON ov.option_id = po.option_id
WHERE (@product_id IS NULL OR po.product_id = @product_id)
  AND NOT EXISTS (
    SELECT 1
    FROM oc_product_option_value pov2
    INNER JOIN oc_option_value ov2 ON pov2.option_value_id = ov2.option_value_id
    WHERE pov2.product_option_id = po.product_option_id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM oc_product_option_value pov3
    WHERE pov3.product_option_id = po.product_option_id
      AND pov3.option_value_id = ov.option_value_id
  );
