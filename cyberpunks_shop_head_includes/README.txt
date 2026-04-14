OpenCart 3.x — JS/CSS в head по правилам (controller / view path).

Что делает
  1) Модуль Extensions → Modules → Cyberpunks Shop Head Includes.
  2) Правила: include/exclude путей (по строке на путь).
  3) Controller-фаза: *, route:… или совпадение с маршрутом.
  4) View-фаза: путь load->view (например product/product_bundle) — вместе с OCMOD cyberpunks_shop_product_templates.
  5) system/library/cyberpunks_shop_head_includes.php — класс CyberpunksShopHeadIncludes.

Миграция с старым cyberpunks_shop_features (правила ассетов)
  Настройки в БД с другим ключом. Перенесите правила вручную в новый модуль или скопируйте значение oc_setting:
  cyberpunks_shop_features_rules → cyberpunks_shop_head_includes_rules (ключ группы cyberpunks_shop_head_includes).

Сборка
  bash build-ocmod.sh

Установка
  Installer → .ocmod.zip → Modifications → Refresh → Modules → Install → Edit
