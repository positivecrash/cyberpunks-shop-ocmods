OpenCart 3.x — per-product catalog view path (load->view), колонка oc_product.cyberpunks_template.

Колонка в БД
  С версии 1.1.0 колонка создаётся сама при первом addProduct/editProduct (SHOW COLUMNS + ALTER), без ручного SQL и без модуля в Extensions.
  Права пользователя БД должны включать ALTER (как у обычного пользователя приложения).
  install.sql оставлен только как справка / если ALTER из PHP запрещён политикой.

Почему был белый экран при сохранении товара
  Чаще всего: OCMOD уже подставлял cyberpunks_template в INSERT/UPDATE, а колонки в таблице не было → ошибка MySQL → HTTP 500.
  Реже: модификации не обновлены (пустой storage/modification), конфликт других OCMOD, нет прав ALTER.

Почему «не работает» cyberpunks_shop_head_includes
  Нужны и патч в system/storage/modification/..., и файл upload → system/library/cyberpunks_shop_head_includes.php на диске. См. README в ocmods/cyberpunks_shop_head_includes.

Docker (репозиторий)
  После смены OCMOD: Extensions → Modifications → Refresh. Том opencart_storage хранит кэш модификаций — при странном поведении см. docker-compose.yml.

Зависимости
  cyberpunks_shop_head_includes — правила JS/CSS по view path (отдельно).

Установка
  1) Installer → cyberpunks_shop_product_templates_*.ocmod.zip
  2) Extensions → Modifications → Refresh

Сборка
  bash build-ocmod.sh
