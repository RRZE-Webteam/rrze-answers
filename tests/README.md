# RRZE Answers tests

The suite uses the real WordPress PHPUnit bootstrap. This matters for list-table
behaviour because `WP_Query`, taxonomies, admin screens and multisite hooks
cannot be tested reliably with isolated stubs.

## Safety

The WordPress test bootstrap deletes and recreates its tables. Never use the
normal development or production database.

By default the suite expects a separate database named `rrze_answers_tests`.
The configuration refuses database names without `test` and refuses the
database configured in the local `wp-config.php`.

Create the empty database once, then install dependencies and run the checks:

```sh
composer install
composer test
composer phpstan
# or both:
composer check
```

The default WordPress core directory is inferred from this plugin's location.
CI and non-standard local setups can override it and the database connection:

```sh
WP_CORE_DIR=/path/to/wordpress \
WP_TESTS_DB_NAME=rrze_answers_tests \
WP_TESTS_DB_USER=root \
WP_TESTS_DB_PASSWORD=secret \
WP_TESTS_DB_HOST=localhost \
composer test
```

The suite runs WordPress in multisite mode through `phpunit.xml.dist`.

## Current integration coverage

- Admin list tables for FAQ, glossary and synonym entries, including legacy
  metadata, source filters, sorting and empty taxonomy filters.
- FAQ and glossary synchronization with simulated WordPress HTTP responses,
  including transport failures, malformed responses, pagination, source
  isolation, taxonomy failures, post-write failures, compensating rollback,
  recoverable Trash cleanup, term ownership, remote URL normalization and
  multisite isolation across the fetcher, store and SyncAPI facade boundaries.
