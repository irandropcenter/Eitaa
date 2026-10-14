#!/usr/bin/env bash
#
# Builds the release package:
#   dist/mw24-eitaa-<version>.zip        installable plugin (dev files excluded via .distignore)
#   dist/mw24-eitaa-<version>-package/   plugin zip + Persian docs for the marketplace download
#
# Usage: bash dev-tools/build-zip.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN="$ROOT/mw24-eitaa"
VERSION=$(grep -m1 -E "^\s*\*\s*Version:" "$PLUGIN/mw24-eitaa.php" | awk '{print $3}')
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
mkdir -p "$DIST"

echo "Building mw24-eitaa $VERSION"
python3 - "$PLUGIN" "$STAGE/mw24-eitaa" <<'PY'
import fnmatch, os, shutil, sys
src, dst = sys.argv[1], sys.argv[2]
patterns = [l.strip() for l in open(os.path.join(src, '.distignore')) if l.strip() and not l.startswith('#')]
def ignored(rel):
    name = os.path.basename(rel)
    for p in patterns:
        if p.startswith('/'):
            if rel == p[1:] or rel.startswith(p[1:] + '/'):
                return True
        elif fnmatch.fnmatch(name, p) or fnmatch.fnmatch(rel, p):
            return True
    return False
for base, dirs, files in os.walk(src):
    rel_base = os.path.relpath(base, src)
    rel_base = '' if rel_base == '.' else rel_base
    dirs[:] = [d for d in dirs if not ignored(os.path.join(rel_base, d) if rel_base else d)]
    for f in files:
        rel = os.path.join(rel_base, f) if rel_base else f
        if ignored(rel):
            continue
        os.makedirs(os.path.join(dst, rel_base), exist_ok=True)
        shutil.copy2(os.path.join(base, f), os.path.join(dst, rel))
PY

ZIP="$DIST/mw24-eitaa-$VERSION.zip"
rm -f "$ZIP"
(cd "$STAGE" && python3 -c "
import os, zipfile, sys
with zipfile.ZipFile(sys.argv[1], 'w', zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk('mw24-eitaa'):
        for f in sorted(files):
            z.write(os.path.join(base, f))
" "$ZIP")

PKG="$DIST/mw24-eitaa-$VERSION-package"
rm -rf "$PKG" && mkdir -p "$PKG"
cp "$ZIP" "$PKG/"
cp -r "$ROOT/docs" "$PKG/docs"
rm -rf "$STAGE"
echo "Plugin zip: $ZIP"
echo "Package:    $PKG"
