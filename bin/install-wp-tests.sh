#!/usr/bin/env bash
# Install matching WordPress core and PHPUnit fixtures. Does not create/drop databases.
set -euo pipefail

WP_VERSION="${WP_VERSION:-6.5}"
CACHE_DIR="${WP_TESTS_CACHE_DIR:-$HOME/.cache/simple-comment-editing-tests}"
TARGET_DIR="$CACHE_DIR/$WP_VERSION"
VERSION="$WP_VERSION"

for tool in curl php svn tar; do
	command -v "$tool" >/dev/null || { printf 'Missing required tool: %s\n' "$tool" >&2; exit 1; }
done

if [[ "$VERSION" == latest ]]; then
	# PHP variables must not be expanded by the shell.
	# shellcheck disable=SC2016
	VERSION=$(curl --fail --silent --show-error --location 'https://api.wordpress.org/core/version-check/1.7/' |
		php -r '$data = json_decode(stream_get_contents(STDIN), true); foreach ($data["offers"] ?? [] as $offer) { if (($offer["response"] ?? "") === "upgrade") { print $offer["version"]; exit; } } exit(1);')
fi
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
	printf 'WP_VERSION must be a release number or latest.\n' >&2
	exit 1
fi

if [[ -f "$TARGET_DIR/installed-version" ]] && [[ "$(<"$TARGET_DIR/installed-version")" == "$VERSION" ]]; then
	printf 'WordPress %s test environment already installed in %s\n' "$VERSION" "$TARGET_DIR"
	exit 0
fi
if [[ -e "$TARGET_DIR" ]]; then
	printf 'Refusing to overwrite an existing/incomplete environment: %s. Use a fresh WP_TESTS_CACHE_DIR.\n' "$TARGET_DIR" >&2
	exit 1
fi

mkdir -p "$TARGET_DIR/wordpress" "$TARGET_DIR/tests"
ARCHIVE=$(mktemp "$TARGET_DIR/wordpress.XXXXXX")
trap 'rm -f "$ARCHIVE"' EXIT
curl --fail --silent --show-error --location "https://wordpress.org/wordpress-$VERSION.tar.gz" --output "$ARCHIVE"
tar --extract --gzip --file "$ARCHIVE" --strip-components=1 --directory "$TARGET_DIR/wordpress"
svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/tags/$VERSION/tests/phpunit/includes" "$TARGET_DIR/tests/includes"
svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/tags/$VERSION/tests/phpunit/data" "$TARGET_DIR/tests/data"
printf '%s\n' "$VERSION" > "$TARGET_DIR/installed-version"
printf 'Installed WordPress %s in %s\n' "$VERSION" "$TARGET_DIR"
