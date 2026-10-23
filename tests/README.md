# Tests

Install development dependencies with Composer, then run the unit suite:

```sh
composer test:unit
```

The unit suite requires `pdo_sqlite` and `proc_open` for isolated regression tests.

## Integration tests

Run the API suite against a local SQLite fixture:

```sh
vendor/bin/phpunit -c tests/phpunit.integration-isolated.xml
```

To test table prefixes:

```sh
BADGES_ISOLATED_PREFIX=pr15_ vendor/bin/phpunit -c tests/phpunit.integration-isolated.xml
```

These configurations create separate fixtures under `tests/integration/tmp`.
CI uses `tests/phpunit.integration.xml` for its database matrix.

## Static analysis

```sh
composer analyse:phpstan
```
