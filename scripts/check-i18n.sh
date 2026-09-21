#!/usr/bin/env bash

set -euo pipefail

if ! command -v msgcmp >/dev/null 2>&1; then
	printf 'GNU gettext is required to validate translation domains.\n' >&2
	exit 1
fi

temp_dir="$(mktemp -d)"
trap 'rm -rf "$temp_dir"' EXIT

domain_pot="$temp_dir/simple-comment-editing.pot"
all_domains_pot="$temp_dir/all-domains.pot"

bash scripts/generate-pot.sh "$domain_pot"
wp i18n make-pot . "$all_domains_pot" \
	--slug=simple-comment-editing \
	--ignore-domain \
	--exclude=dist,lib,languages \
	--headers='{"POT-Creation-Date":""}'

if ! msgcmp --use-untranslated --no-fuzzy-matching "$domain_pot" "$all_domains_pot"; then
	printf 'Every gettext call must use the simple-comment-editing text domain.\n' >&2
	exit 1
fi

if ! cmp -s "$domain_pot" languages/simple-comment-editing.pot; then
	diff -u languages/simple-comment-editing.pot "$domain_pot" || true
	printf 'The committed POT is stale. Run npm run i18n:pot.\n' >&2
	exit 1
fi

printf 'Translation template and text domains are valid.\n'
