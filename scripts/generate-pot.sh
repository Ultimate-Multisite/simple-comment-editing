#!/usr/bin/env bash

set -euo pipefail

destination="${1:-languages/simple-comment-editing.pot}"

if ! command -v wp >/dev/null 2>&1; then
	printf 'WP-CLI is required to generate the translation template.\n' >&2
	exit 1
fi

wp i18n make-pot . "$destination" \
	--slug=simple-comment-editing \
	--domain=simple-comment-editing \
	--exclude=dist,lib,languages \
	--headers='{"POT-Creation-Date":""}'
