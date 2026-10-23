# Tests

Install development dependencies with Composer, then run the unit suite:

```sh
composer test:unit
```

The unit suite requires `pdo_sqlite` and `proc_open` for isolated regression tests.

## Integration tests

Configure a dedicated MySQL test database using `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. Set `DB_PREFIX` to test table
prefixes and `FLARUM_TEST_TMP_DIR_LOCAL` to select the test storage directory.

The setup command drops all tables in the configured database.

```sh
composer test:setup
composer test:integration
```

## Static analysis

```sh
composer analyse:phpstan
```
