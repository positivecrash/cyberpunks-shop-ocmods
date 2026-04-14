# Cyberpunks Shop OCMODs

This repository stores standalone OCMOD packages used by the Cyberpunks Shop OpenCart project.

## Build from repository root

Use the shared build script and pass a module folder name:

```bash
./build-ocmod.sh <module-folder>
```

Examples:

```bash
./build-ocmod.sh cyberpunks_shop_features
./build-ocmod.sh cyberpunks_shop_head_includes
./build-ocmod.sh cyberpunks_shop_product_templates
```

The script reads `<code>` and `<version>` from the target `install.xml` and creates:

`<code>_<version_with_underscores>.ocmod.zip`

inside the same module folder.