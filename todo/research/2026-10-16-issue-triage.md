# Open-issue review and dependency investigation

Reviewed 2026-10-16 against master `4981a8f`, including all 17 open issues,
their current source premises, and the six open draft pull requests.

## Issue disposition

| Issues | Result |
| --- | --- |
| #27 | Closed: both comment-text filters already receive the third argument in `includes/Functions.php:84`. |
| #33 | Closed: settings and `editing.js` provide the requested compact countdown; the historical console error itself was not reproduced. |
| #29 | Closed: `README.md` documents HTML customization through `sce_buttons`, separately from escaped text labels. |
| #38 | Closed: `README.md` documents the native `comment_text()` custom-loop integration omitted by the reported loop. |
| #28 | Closed as not planned: the historical thread never identifies the third-party plugin or a reproducible case. This is not a compatibility guarantee. |
| #79 | Selected for implementation: the live dependency alert and both dependency paths are confirmed. Partial mitigation below; retain the issue and alert. |
| #89, #90, #93, #100, #101 | Retain: draft PRs #105, #118, #119, #120, #121 already implement these, but runtime verification is outstanding. Do not duplicate or treat drafts as delivered fixes. |
| #95, #96, #102 | Retain: current third-party/site-specific reproduction remains unverified. Age alone does not establish resolution. |
| #103 | Retain: parent tracker has outstanding follow-ups; the initial support audit being complete does not complete its children. |
| #51, #58 | Retain: persistent operational dashboards, not implementation tasks. |

The unrelated translation draft #57 also remains open.

## GH#79: confirmed dependency paths

Before this change, `npm explain sprintf-js` reports:

- `@wordpress/i18n@4.20.0 → sprintf-js@1.1.2`.
- `grunt@1.6.3 → grunt-legacy-util@2.0.2 → underscore.string@3.3.6 → sprintf-js@1.1.2`.

Although npm marks these development dependencies, webpack consumes the direct
translation dependency in the deployed `dist/integrations-admin.js` bundle.
The frontend editing entry instead reads WordPress's global translation API;
its generated JavaScript is unchanged by this update.

Upgrade the direct `@wordpress/i18n` requirement to `^6.30.0`. Its formatter
uses `@tannin/sprintf`, already present elsewhere in the dependency graph,
instead of `sprintf-js`. npm regenerates the lockfile and deduplicates newer
WordPress hooks/translation dependencies. Rebuild the admin bundle normally;
do not edit minified code. The new package requires Node >=18.12, already
required by other locked WordPress build dependencies; this does not change
the plugin's PHP or WordPress minimum.

## Verification and limits

- `npm ci --ignore-scripts --no-audit --no-fund` reproduced the original graph.
- `npm install --ignore-scripts --no-audit --no-fund` resolved the scoped update.
- Inspected the installed old/new translation exports and formatter modules.
  Node assertions passed for exports, untranslated/translated text, plural
  selection, and positional substitution.
- A broader exploratory assertion found `%02d` padding differs in the newer
  formatter. No source call uses that feature: the only direct source import
  is `Mailchimp.js`, which calls `__` and has an unused `sprintf` import.
  Compact countdown formatting is implemented separately. Do not assume the
  new formatter is a drop-in replacement for every historical format string.
- `npm run lint:js`: exit 0, **118 existing warnings**, not warning-free.
  Source/config lint inputs are unchanged by this dependency update.
- `npm run build`: success, three admin-bundle size warnings. The generated
  admin JavaScript changes; frontend JavaScript and CSS do not.
- `npm audit`: before 22 findings (17 high, 5 moderate); after 21 (17 high,
  4 moderate). The `@wordpress/i18n` finding disappears. Audit still exits 1;
  this is not a clean dependency audit.
- No WordPress admin/browser integration result is claimed by these Node and
  build checks. Keep the implementation draft until that path is verified.

## Outstanding work

GH#79 remains open: the Grunt packaging path still installs `sprintf-js`.
The npm registry's newest release is 1.1.3, still inside the advisory range;
GitHub reports no patched version. Do not override to 1.1.3 as a supposed fix,
silently dismiss the alert, or use `npm audit fix --force` to downgrade tools.

Next investigate removal/replacement of the legacy archive task in
`gruntfile.js`, preserving its package-file selection, or a verified upstream
dependency fix. Review packaging callers before changing that workflow, then
validate archive contents, rebuild, rerun lint/audit, and recheck the live
dependency alert. Any risk acceptance must explicitly describe this remaining
development-tool exposure rather than claim complete remediation.

Other audit findings affect the lint/build/packaging graph (including the
WordPress ESLint tooling, glob dependencies, Sass and fast-uri). They remain
outside this scoped translation-library change and are not fixed by it.
