# Cyberpunks Shop Twig Partials

OpenCart 3.x OCMOD.

## What it does

Enables Twig partial includes in OpenCart templates, for example:

```twig
{% include 'partials/file.twig' %}
```

Optional partial:

```twig
{% include 'optional.twig' ignore missing %}
```

Supports passing many parameters to partials via native Twig `include ... with`:

```twig
{% set partial_params = {
  product_id: product_id,
  heading_title: heading_title,
  tags: tags,
  stock: stock,
  price: price,
  special: special
} %}

{% include 'partials/tips_terms.twig' with partial_params %}
```

Behavior:
- Use relative include from current template folder: `{% include 'partials/file.twig' %}`
- Use include from template root (enabled by this OCMOD): `{% include 'common/partials/custom_checkbox.twig' %}`
- Pass params with `with`: `{% include 'partials/file.twig' with { key: value } %}`

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_twig_partials
```

## Install

`Extensions -> Installer -> upload .ocmod.zip -> Modifications -> Refresh`
