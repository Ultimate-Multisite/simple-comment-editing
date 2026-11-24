#!/usr/bin/env bash
# Deploy an already-built release ZIP to WordPress.org trunk and tag it,
# without changing the ZIP's contents. Assets (banners, icons, screenshots)
# live only in SVN and are never touched here.
#
# Usage: VERSION=3.4.3 SVN_USERNAME=... SVN_PASSWORD=... bin/deploy-wporg.sh build/simple-comment-editing-3.4.3.zip
set -euo pipefail

: "${VERSION:?VERSION is required}"
: "${SVN_USERNAME:?SVN_USERNAME is required}"
: "${SVN_PASSWORD:?SVN_PASSWORD is required}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
	printf 'Refusing to deploy non-stable version %s\n' "$VERSION" >&2
	exit 1
}

SLUG='simple-comment-editing'
ARCHIVE="$(realpath "${1:?Release ZIP is required}")"
DEPLOY_DIR="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/wporg-deploy.XXXXXX")"
SVN_URL="https://plugins.svn.wordpress.org/${SLUG}"

# Refuse to update trunk for a version that is already tagged.
if svn info "${SVN_URL}/tags/${VERSION}" >/dev/null 2>&1; then
	printf 'SVN tag %s already exists; refusing to overwrite it\n' "$VERSION" >&2
	exit 1
fi

python3 - "$ARCHIVE" "$DEPLOY_DIR/package" "$VERSION" "$SLUG" <<'PY'
import pathlib
import re
import sys
import zipfile

archive, destination, version, slug = sys.argv[1:]
with zipfile.ZipFile(archive) as package:
    for name in package.namelist():
        path = pathlib.PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0] != slug:
            raise SystemExit('Unexpected release ZIP path')
    header = package.read(f'{slug}/index.php').decode()
    readme = package.read(f'{slug}/readme.txt').decode()
    if re.search(r'^\s*\*\s*Version:\s*(\S+)', header, re.M)[1] != version:
        raise SystemExit('Release ZIP plugin version mismatch')
    if re.search(r'^Stable tag:\s*(\S+)', readme, re.M)[1] != version:
        raise SystemExit('Release ZIP stable tag mismatch')
    package.extractall(destination)
PY

svn checkout --depth=immediates "$SVN_URL" "$DEPLOY_DIR/svn"
svn update --set-depth=infinity "$DEPLOY_DIR/svn/trunk"
rsync -rc --delete "$DEPLOY_DIR/package/${SLUG}/" "$DEPLOY_DIR/svn/trunk/" \
	--exclude='.svn' --exclude='.git' --exclude='.github'
cd "$DEPLOY_DIR/svn"

# XML preserves whitespace and quotes, unlike awk/xargs over text status.
# Subprocess argument arrays also prevent filenames from becoming options.
python3 - <<'PY'
import subprocess
import xml.etree.ElementTree as ET

status = ET.fromstring(subprocess.check_output(['svn', 'status', '--xml', 'trunk']))
for state, operation in [('unversioned', ['add', '--parents', '-q']), ('missing', ['delete', '--force', '-q'])]:
    # A trailing @ escapes SVN's peg-revision syntax in literal filenames.
    paths = [entry.attrib['path'] + '@' for entry in status.findall('.//entry')
             if entry.find('wc-status').attrib['item'] == state]
    for offset in range(0, len(paths), 500):
        subprocess.run(['svn', *operation, '--', *paths[offset:offset + 500]], check=True)
PY

if [[ -n "$(svn status trunk)" ]]; then
	svn commit trunk --username "$SVN_USERNAME" --password "$SVN_PASSWORD" \
		--no-auth-cache --non-interactive -m "Update trunk to ${VERSION}"
fi
svn copy "${SVN_URL}/trunk" "${SVN_URL}/tags/${VERSION}" \
	--username "$SVN_USERNAME" --password "$SVN_PASSWORD" \
	--no-auth-cache --non-interactive -m "Tag ${VERSION}"
printf 'Successfully deployed and tagged %s on WordPress.org\n' "$VERSION"
