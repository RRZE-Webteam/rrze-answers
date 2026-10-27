# RRZE Answers tests

Version 2.0 targets WordPress 7.0+ and declares PHP 8.2+ support. For this rollout,
WordPress 7.0 and the current local WordPress are tested with the existing
PHP 8.5.12 runtime. PHP 8.2 testing is explicitly excluded from this review;
the declared PHP minimum is not evidence of a completed minimum-runtime test.
Older saved plugin content remains an upgrade fixture; older WordPress runtimes
and code-only downgrades to 1.x are outside the 2.x release contract.

See the [2.0 validation results](RELEASE-2.0-VALIDATION.md) for the completed
local matrix, separate implementation commits and remaining rollout conditions.

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

## Upgrade from v1.4.6

```sh
composer test:upgrade
RRZE_UPGRADE_REFERENCE=main composer test:upgrade
```

This requires the Git history containing `v1.4.6`, installed Composer/npm
dependencies and the same separate test database as the PHPUnit suite. It uses
the fixed `rrze_upgrade_tests_` table prefix and recreates only those tables,
including secondary-site tables from interrupted runs. Do not run two upgrade
checks concurrently against the same database. The normal PHPUnit tables use a
different prefix.

To test a second WordPress installation, set `WP_CORE_DIR` and, if its directory
has no configuration file, point `WP_TESTS_SOURCE_CONFIG` to the normal local
`wp-config.php`. That file supplies connection settings and the database name to
exclude; the test database remains `rrze_answers_tests` (or `WP_TESTS_DB_NAME`).

The check starts the old and current plugins in separate PHP processes against
the same stored data, then saves migrated blocks through the REST API. Failed
runs retain diagnostic files in the printed temporary directory. Successful runs
remove their temporary checkout. See [fixture provenance and coverage](fixtures/v1.4.6/README.md).

## Current integration coverage

- Admin list tables for FAQ, glossary and synonym entries, including legacy
  metadata, source filters, sorting and empty taxonomy filters.
- FAQ and glossary synchronization with simulated WordPress HTTP responses,
  including transport failures, malformed responses, pagination, source
  isolation, taxonomy failures, post-write failures, compensating rollback,
  recoverable Trash cleanup, term ownership, remote URL normalization and
  multisite isolation across the fetcher, store and SyncAPI facade boundaries.
- Producer/consumer REST compatibility for native source term IDs, WordPress
  embedded terms, remote taxonomy lookup and the collision-free name fields
  emitted by current plugin versions.
- Recoverable synchronized-source removal, including atomic rollback, retained
  taxonomy relationships, settings consistency and multisite isolation.
- Public embeds across all four entry types: unpublished and password-protected
  content, wrong post types, missing IDs, ordered multiple selections, legacy
  block attributes, slug precedence and language filters.
- Escaped synonym output and sanitized legacy placeholder HTML, including inline
  snapshots, nested blocks and cyclic references.
- Shared FAQ/glossary rendering: explicit and filtered lists, combined taxonomy
  and language filters, sorting, A–Z and tag-cloud navigation, grouped output,
  legacy aliases and display options, and schema isolation for nested lists.
- Large accordion collections: 1,000 consecutive entries and single answers
  larger than 1 MB with the normal PCRE backtrack limit, for both Elements and
  Core. Regression checks preserve item order, group separators, schema output
  and the first item's `load_open` state.

## Editor checks

```sh
npm run test:unit -- --runInBand --watch=false
RRZE_ROLLOUT_WP70_SSR=/path/to/wordpress-7.0/wp-includes/js/dist/server-side-render.js \
npm run test:unit -- --runInBand --watch=false tests/js/rollout-editor-compatibility.test.js
npm run typecheck
npm run build
```

The JavaScript suite parses, migrates, serializes and reopens legacy fixtures for
all five blocks with the Gutenberg parser. REST pagination tests exercise the
WordPress apiFetch middleware for empty collections, exact page boundaries,
multiple pages and failures on later pages. These are automated checks, not a
replacement for testing the editor with supported WordPress versions and themes.
