# PHPUnit integration tests

The suite uses PHPUnit 9.6, Yoast PHPUnit Polyfills 1.x, and WordPress's real
`WP_UnitTestCase` / `WP_Ajax_UnitTestCase` framework, following the pattern used in
Ultimate Multisite. Test tooling targets PHP **7.4+**; this does not change the
plugin's advertised runtime minimum. Composer resolves the lockfile against PHP
7.4.33, and CI checks the actual platform requirements on each runtime.

## Setup

Requirements: PHP 7.4+ with PHPUnit's required extensions and `mysqli`, Composer,
curl, Subversion, tar, and a **disposable** MySQL/MariaDB database. Docker is
optional; it provides a convenient isolated database:

```sh
composer install
docker run --detach --name sce-phpunit-db \
  --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
  --env MARIADB_DATABASE=sce_test \
  --publish 127.0.0.1:33077:3306 mariadb:11.4
export WP_TEST_DB_NAME=sce_test
export WP_TEST_DB_HOST=127.0.0.1:33077
export WP_VERSION=6.5
bash bin/install-wp-tests.sh
composer test
```

Wait for the database to be ready before running the suite. The empty-password
database above is bound only to loopback and must never be used for real data.
For an existing **test-only** database, set `WP_TEST_DB_USER` and
`WP_TEST_DB_PASSWORD` through your local environment/secret manager as needed.
Do not commit credentials.

**Warning:** WordPress recreates test tables on every suite run. The database name
must end in `_test`, and tables use `scetests_`, but these safeguards do not make
it safe to use a development or production database. Never share a test database
between parallel runs. The installer downloads files only; it does not create,
drop, or modify databases.

WordPress core and matching test fixtures are stored outside the repository in
`$HOME/.cache/simple-comment-editing-tests/<WP_VERSION>/`. Set
`WP_TESTS_CACHE_DIR` to use another cache root. The installer accepts a specific
release or `WP_VERSION=latest`. It refuses to overwrite incomplete or outdated
environments; use a fresh cache root to retry or refresh `latest`. Existing test
installations may be used by setting both `WP_TESTS_DIR` and `WP_CORE_DIR`.

## Commands

```sh
composer test                                    # Complete integration suite
composer test -- --filter Functions_Test         # Ownership and expiry
composer test -- --filter Ajax_Test              # Real AJAX hooks and DB effects
composer test -- --order-by=random --random-order-seed=42
WP_MULTISITE=1 composer test                     # Network-active plugin
XDEBUG_MODE=coverage composer test:coverage      # Xdebug or PCOV required
composer lint                                   # PHPCS and PHPStan
```

CI runs the minimum combination (PHP 7.4 / WordPress 6.5), current WordPress on
PHP 8.2–8.5, and a multisite run. Coverage is generated on one job only; there is
no arbitrary percentage gate. JUnit results are uploaded even on failure.
The stable **PHPUnit checks** PR check succeeds only when every matrix job
passes; failed, cancelled, or skipped matrix jobs cannot produce a green check.
This is the check name to select if enabling required checks in branch protection.
Use the minimum WordPress release with its matching older PHP runtime: running
WordPress 6.5 on PHP 8.4+ produces upstream deprecations.

## Coverage and conventions

- Real user/post/comment factories, WordPress database storage, and AJAX hooks;
  no mocked WordPress permissions or request-input functions.
- Valid, missing, invalid, and array-valued nonces; rejection must leave comment
  content, status, and ownership metadata unchanged.
- Anonymous cookie ownership, logged-in authors, other visitors, post mismatches,
  expiry boundaries and hour/day rollover, and unlimited-editing restrictions.
- Save persistence, quotes/backslashes, unsafe HTML, empty content, trash versus
  permanent deletion, disabled deletion, timer revocation, and time remaining.
- Settings defaults, boolean/timer validation, safe deletion fallbacks, text
  sanitization, and persistence on single-site and multisite.
- Test methods use `test_*`, files end in `Test.php`, providers are public static
  methods, and each test has meaningful assertions. No sleeps or live APIs.
- WordPress rolls back database changes and restores hooks; the shared trait
  additionally restores request state and private static plugin caches.
  The request-wide `DOING_SCE` constant is intentionally idempotent; tests must
  not assume that constants can be reset between AJAX requests.
- Empty suites, risky tests, and PHPUnit warnings fail the run. PHP errors and
  deprecations are surfaced rather than globally suppressed.

To remove the disposable database created above when finished:

```sh
docker stop sce-phpunit-db
docker rm sce-phpunit-db
```
