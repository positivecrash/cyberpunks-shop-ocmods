#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

CODE=$(sed -n 's/^[[:space:]]*<code>\([^<]*\)<\/code>.*/\1/p' install.xml | head -1)
VERSION=$(sed -n 's/^[[:space:]]*<version>\([^<]*\)<\/version>.*/\1/p' install.xml | head -1)
VER_SUFFIX=$(echo "${VERSION}" | tr '.' '_')
OUT="${CODE}_${VER_SUFFIX}.ocmod.zip"
rm -f "${OUT}"

files=("install.xml" "upload")
shopt -s nullglob nocaseglob
for p in README* CHANGELOG* *.md *.txt; do
	[[ -f "$p" ]] || continue
	[[ "$p" == "install.xml" ]] || [[ "$p" == "$OUT" ]] && continue
	files+=("$p")
done
shopt -u nocaseglob nullglob

zip -r "${OUT}" "${files[@]}"
echo "${OUT}"
