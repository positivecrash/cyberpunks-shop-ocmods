#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 <module-folder>" >&2
	echo "Example: $0 cyberpunks_shop_head_includes" >&2
	exit 1
fi

TARGET_INPUT="$1"
if [[ "$TARGET_INPUT" = /* ]]; then
	TARGET_DIR="$TARGET_INPUT"
else
	TARGET_DIR="$SCRIPT_DIR/$TARGET_INPUT"
fi

if [[ ! -d "$TARGET_DIR" ]]; then
	echo "error: target directory not found: $TARGET_DIR" >&2
	exit 1
fi

if [[ ! -f "$TARGET_DIR/install.xml" ]]; then
	echo "error: install.xml not found in: $TARGET_DIR" >&2
	exit 1
fi

cd "$TARGET_DIR"

CODE=$(sed -n 's/^[[:space:]]*<code>\([^<]*\)<\/code>.*/\1/p' install.xml | head -1)
if [[ -z "${CODE}" ]]; then
	echo "error: could not read <code> from install.xml" >&2
	exit 1
fi

VERSION=$(sed -n 's/^[[:space:]]*<version>\([^<]*\)<\/version>.*/\1/p' install.xml | head -1)
if [[ -z "${VERSION}" ]]; then
	echo "error: could not read <version> from install.xml" >&2
	exit 1
fi

VER_SUFFIX=$(echo "${VERSION}" | tr '.' '_')
OUT="${CODE}_${VER_SUFFIX}.ocmod.zip"

rm -f "${OUT}"

files=("install.xml")
[[ -d upload && -n "$(ls -A upload 2>/dev/null)" ]] && files+=("upload")
[[ -f install.sql ]] && files+=("install.sql")

shopt -s nullglob nocaseglob
for p in README* CHANGELOG* *.md *.txt; do
	[[ -f "$p" ]] || continue
	[[ "$p" == "install.xml" ]] && continue
	[[ "$p" == "$OUT" ]] && continue
	files+=("$p")
done
shopt -u nocaseglob nullglob

zip -r "${OUT}" "${files[@]}"
echo "${TARGET_DIR}/${OUT}"
