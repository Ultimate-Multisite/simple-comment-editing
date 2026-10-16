# simple-comment-editing

<!-- AI-CONTEXT-START -->

## Quick Reference

- **Build**: `npm run build`
- **Test**: `composer test` (PHP 7.4+, dedicated database; see `tests/README.md`)
- **Quality**: `composer lint` and `npm run lint:js`
- **Deploy**: `# TODO: add deploy command`

## Local WordPress browser testing

All plugins can use the shared existing local WordPress installation. Its root
is stored in the machine-local Git setting `sce.wordpressPath` (configured on
this development machine), not in public repository content. On another machine,
set that local Git setting to the existing WordPress root first. Test the actual
feature/PR worktree, not the canonical checkout or another branch. Build its
assets before opening the browser.

```sh
# Run from the linked worktree being tested.
WP_SITE_PATH="$(git config --get sce.wordpressPath)"
test -n "$WP_SITE_PATH" && test -f "$WP_SITE_PATH/wp-load.php" || exit 1
npm run build
# Inspect any existing destination first; never overwrite another session's link.
ls -ld "$WP_SITE_PATH/wp-content/plugins/simple-comment-editing"
ln -s "$PWD" "$WP_SITE_PATH/wp-content/plugins/simple-comment-editing"
wp --path="$WP_SITE_PATH" plugin activate simple-comment-editing
wp --path="$WP_SITE_PATH" option get siteurl
```

If the destination already points at this worktree, skip `ln -s`. If it points
elsewhere, coordinate before changing it; do not delete an existing directory.
For other plugins, substitute their plugin slug. Activation above is site-local;
do not network-activate or reset the shared installation without task-specific
authorization. Discover the current URL with WP-CLI rather than guessing it.

Use a real browser (`agent-browser` is available), with separate named sessions
for the guest owner, a logged-in subscriber, and a non-owner. Create a dedicated
test post with comments enabled. Verify submission, immediate Edit, Save, Cancel,
timer updates, and denied non-owner requests; inspect browser console/network
and confirm saved content with WP-CLI. Never publish browser cookies or passwords.

For dynamic-comment changes, test actual Ajaxify Comments and Lazy Load for
Comments separately. Ajaxify must be enabled in its settings after activation.
On Twenty Twenty-Five, set its comments-container selector to
`.wp-block-comments`: the default selector also matches the `#comments` heading,
causing a full-page fallback. Set a JavaScript page marker before submission and
confirm it survives, so a reload cannot be mistaken for working AJAX. Repeat
`SCE.init()` and the WP Ajaxify callbacks and check that requests/handlers do not
duplicate. Respect WordPress's comment-flood interval when switching browsers.

Keep the site and plugin symlink available for follow-up work. Restore temporary
integration settings/activation changes and remove only fixtures you created
when testing is complete; do not disturb other plugins, users, or content.

This browser site is **not** the PHPUnit database. `composer test` recreates
tables: use only a disposable `_test` database and environment credentials as
documented in `tests/README.md`, never the site's development database. Run
single-site and `WP_MULTISITE=1` sequentially, not against a shared database
concurrently. A green PHP suite does not replace real browser verification.

## Project Overview

<!-- Brief description of what this project does and why it exists. -->

## Architecture

<!-- Key architectural decisions, tech stack, directory structure. -->

## Conventions

- Commits: [Conventional Commits](https://www.conventionalcommits.org/)
- Branches: `feature/`, `bugfix/`, `hotfix/`, `refactor/`, `chore/`

## Key Files

| File | Purpose |
|------|---------|
| `.agents/AGENTS.md` | Project-specific agent instructions |
| `TODO.md` | Task tracking |
| `CHANGELOG.md` | Version history |

<!-- AI-CONTEXT-END -->
