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

## Build

From repository root:

```bash
./build-ocmod.sh cyberpunks_shop_twig_partials
```

## Install

`Extensions -> Installer -> upload .ocmod.zip -> Modifications -> Refresh`
