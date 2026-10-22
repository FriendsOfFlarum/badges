# Unit tests

Run from the package root with its installed development dependencies:

```sh
composer test:unit
```

The equivalent explicit local PHPUnit command is:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php -c tests/phpunit.unit.xml --do-not-cache-result
```

`bootstrap.php` prefers the package's `vendor/autoload.php`, then searches
containing directories for a workspace install. It explicitly registers this
checkout's extension and test PSR-4 namespaces. It does not boot Flarum.

`unit/IsolatedRegressionTest.php` runs the optimizer, visibility, and default
translation scripts in fresh PHP subprocesses using the current PHP binary.
This requires `proc_open`. Optimizer and visibility checks use only SQLite
`:memory:` (requiring PDO SQLite); translation checks use in-memory model
doubles. Process isolation prevents those doubles, Eloquent's resolver,
container state, and registered preferences from leaking into other unit tests.
Keep these scripts outside the PHPUnit discovery directory and do not include
them directly in another test process.

The wrappers check both the subprocess exit status and its completion output.
Optimizer/visibility completion totals ensure core callbacks cannot silently
stop being exercised. PHPUnit reports each script as one test with two
assertions; checks inside the scripts are additional to that assertion total.
The scripts can also be run directly with PHP for diagnostic output.

## JSON API integration verification

The installed Flarum 1 testing package requires MySQL. Its stock `SetupScript`
prints credentials and drops all target database tables. Do not use that script
with forum credentials. `UsesTmpDir::tmpDir()` prefers
`FLARUM_TEST_TMP_DIR_LOCAL`, then `FLARUM_TEST_TMP_DIR`, and otherwise uses the
testing package's own tmp folder. `TestCase::app()` includes `config.php` from
that folder (this version has no testing `Bootstrapper` class).
`Installation::getConfigPath()` resolves its config path relative to `Paths::base`.

### Local verification status (2026-10-22)

An authorized connection to the test forum's MySQL server succeeded, but
`CREATE DATABASE` was denied with SQLSTATE `42000`, MySQL error `1044`.
The last requested database was `fof_badges_test_1x_20261022_e633e9ed`;
it was **not created**. Following explicit authorization for prefix isolation,
direct core `Installation` succeeded in the configured test-forum database with
new prefix **`bv233d97_`**. Flarum 1's `DatabaseConfig` limits prefixes to 10
characters, so the helper uses `bv` + six random hexadecimal characters + `_`.

**Full integration suite: 79 tests / 261 assertions passed.**
**Unit suite: 91 tests / 139 assertions passed.**
No production failures or test-expectation adjustments were needed.

`integration/api/VisibilityResponseTest.php` adds full HTTP expectations for
category counts and includes with preloaded/unloaded badge relationships, and
owner/guest/stranger/admin privacy in final award and profile responses. These
11 provider cases all passed against MySQL.

`integration/api/ActionAuthorizationTest.php` ports the 2.x action/security
coverage to PHPUnit 9 data providers and Flarum 1 table-name fixtures. Its 17
cases cover action persistence/clearing, moderator restrictions on group rewards
and eligibility, permitted metadata/notification edits, and malformed actions
rejected without database mutation. All 17 cases passed against MySQL.

### Isolation evidence

- Before installation, metadata showed no table with the selected prefix. The
  helper saved all **104 preexisting table names** and normalized schema hashes.
- `Migrator::installFromSchema()` substitutes the configured prefix for every
  `db_prefix_` occurrence. Its `DROP TABLE IF EXISTS` targets therefore did not
  exist. No database-wide wipe/drop/flush API was called.
- Outstanding core migrations use the connection's schema/query builders;
  raw timestamp `ALTER TABLE` statements explicitly use `getTablePrefix()`.
  Badge migrations use the same prefixed builders. `prefix_indexes` is true,
  and foreign-key targets are wrapped with the table prefix.
- Installation enabled `[]`; every API test enables only `fof-badges`.
  The migration table contains no other extension IDs.
- Core installation added **17 tables**; badge migrations added **4**, for
  **21 prefix-only additions**. All 104 preexisting tables remained present,
  with unchanged schemas (excluding live AUTO_INCREMENT counters). No foreign
  key from the isolated tables points outside the isolated prefix.
- Test teardown rolls back its transaction and flushes only the local FileStore
  under `integration/tmp/prefix-isolated/storage/cache`. Post-run checks found
  only the installation administrator, empty badge and access-token tables,
  and persisted `extensions_enabled = []`.

The metadata checks establish table/schema isolation; they are not a snapshot
of live forum row contents. Mutation scoping was also checked in the execution
paths above.

### Local rerun

The ignored, installation-specific `integration/tmp/prefix-verification.php`
reads the authorized forum config internally. Its `setup` mode selects an unused
random prefix, saves the baseline, and runs direct core installation. It refuses
to reinstall an existing local test environment. Setup has already completed.
Its `run` mode checks the target, captures raw PHPUnit output locally, redacts
connection details before displaying output, and verifies isolation again.
The helper and generated files are local and not included in a checkout.

Run from the package root:

```sh
php tests/integration/tmp/prefix-verification.php verify
php tests/integration/tmp/prefix-verification.php run
vendor/bin/phpunit -c tests/phpunit.unit.xml --do-not-cache-result
```

Database-free discovery:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php \
  -c tests/phpunit.integration.xml --list-tests
```

### Local artifacts and cleanup

All generated config, assets, FileStore cache, PHPUnit subprocess temporary files,
logs, and metadata are under ignored `integration/tmp/prefix-isolated`, created
with a restrictive umask. `config.php` contains credentials; do not print or
commit it. `phpunit.raw.log` and `phpunit.stderr.raw.log` are private raw captures;
`phpunit.redacted.log` is the shareable test output. `isolation.json` preserves
the preexisting table inventory/schema hashes, and `tables-after.json` preserves
the final inventory.

The 21 isolated tables are retained for reruns. To clean up later, derive an
explicit table allowlist from the final inventory minus the saved baseline,
require every name to start literally with `bv233d97_`, check that no foreign
keys from outside that allowlist reference it, and drop only those exact tables.
Do not use database-wide cleanup or an unescaped SQL LIKE prefix (underscores
are wildcards). After scoped database cleanup, remove only the generated
`integration/tmp/prefix-isolated` directory. The earlier ignored
`integration/tmp/setup-isolated.php` remains the unused separate-database helper.
