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

## Isolated JSON API integration tests

Run from this extension root:

```sh
vendor/bin/phpunit -c tests/phpunit.integration-isolated.xml --do-not-cache-result
```

This optional configuration loads `integration/isolated-bootstrap.php`.
The regular `phpunit.integration.xml` retains the CI database matrix configuration.
It explicitly overrides the database and test-directory environment variables,
uses SQLite at `tests/integration/tmp/isolated-api/api-verification.sqlite`, and keeps the
generated Flarum config, public assets and storage under that same ignored
directory. It validates an existing test config before tests boot Flarum.
Application config is never loaded. No external database is used.

The installed Flarum 2 testing `SetupScript` supports SQLite, but drops all
tables during initial installation and prints connection settings. The
bootstrap confines setup to this fixture and suppresses that output. It creates
the empty SQLite file before setup because core's `DatabaseConfig::prepare()`
otherwise prepends the base path to a nonexistent absolute filename.

`ActionAuthorizationTest` verifies HTTP create/update serialization, persisted
JSON casts, admin-only group actions and eligibility changes, moderator metadata
edits, invalid action rejection and unchanged database rows after rejection.
`VisibilityResponseTest` verifies complete category documents with both loaded
and unloaded badge relationships, counts and includes; catalog category privacy;
owner/guest/stranger/admin/manager award visibility; and final profile, post and
discussion decorations.

Fixture details verified against source:

- `migrations/2026_01_23_000003_add_badge_permissions.php` grants viewing to
  guests. No-permission cases explicitly remove that grant inside the test
  transaction.
- `UserBadgeHelper::getUserBadges()` filters card-hidden awards even for owners;
  `getPrimaryBadge()` falls back to a visible award when the primary is hidden.
- Core `DiscussionResource` exposes `posts` linkage but does not make it
  includable. Nested response tests use `firstPost.user` and `lastPost.user`.

## PR #15 batch-loading verification

The batch is scoped to the request through a weak-key map. Raw relationships are
shared within that batch; decoration visibility is evaluated separately for each
actor. Partially loaded nested relationships and deliberately constrained award
collections are preserved.

`unit/UserBadgeBatchTest.php` covers request isolation, abandoned request cleanup,
actor changes, partial preloads and duplicate user instances.
`integration/api/ListUserBadgesQueryCountTest.php` checks all four decoration
fields and query counts for `user`, `grantedByUser` and `badge` includes with page
sizes 2 and 8. Query matching uses the actual connection's prefix and quoting,
with positive controls so an unmatched query cannot silently pass.

The same integration suite can use a separate prefixed SQLite fixture:

```sh
BADGES_ISOLATED_PREFIX=pr15_ vendor/bin/phpunit \
  -c tests/phpunit.integration-isolated.xml --do-not-cache-result
```

This fixture lives in `integration/tmp/isolated-api-pr15-prefix`; it does not
change the default isolated database or CI's database matrix.

Verification: 96 unit tests / 211 assertions, and 93 integration tests / 546
assertions passed. The complete integration suite passed both without a prefix
and with `pr15_`. PHPStan level 6 passed with no errors.

Browser smoke checks are listed in [MANUAL.md](MANUAL.md).
