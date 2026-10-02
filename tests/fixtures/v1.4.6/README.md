# v1.4.6 upgrade fixture

Source: tag `v1.4.6`, commit `fe1c7a8f8a913778feb3ef4960880ffe6c0a2170`.
`block-attributes.json` contains the attribute schemas extracted verbatim from
that commit's five `blocks/*/block.json` files. `blocks.json` contains synthetic
saved blocks using those schemas, with explicit expectations for the new editor.
The fixture contains no production content or credentials.

`composer test:upgrade` exports the pinned commit into a temporary directory and
boots its unmodified PHP code. It creates two sites containing local and imported
FAQ/glossary entries, synonyms, encoded HTML placeholders, a draft, taxonomy
relationships, linked category pages, custom slugs, source settings and a sync
schedule. It saves individual blocks, a page combining all block types, classic shortcode aliases and inline
formats using that old WordPress/plugin combination.

Fresh PHP processes then load the current plugin against the same database. Raw
database snapshots check that startup and rendering do not rewrite the old
content, metadata, terms, relationships, options or schedule. Separate requests
cover both sites' slug registration. The Gutenberg tests parse, migrate, serialize
and reopen the saved blocks. The runner submits that serialization to the real
REST page controller as an editor, compares frontend output before and after
saving, checks unrelated records and starts another request against the saved
state. A mocked first synchronization updates legacy imports in place, retains
their anchors and term relationships, and does not duplicate them when repeated.
External HTTP requests are blocked and unexpected attempts fail the test.

Only generated wrapper/index IDs and the once-per-request FAQPage scope are
normalized when comparing the current frontend before and after an editor save.
The old/new plugin comparison checks retained content and selection boundaries;
it does not require byte-identical HTML across the frontend refactoring.

## Scope

This covers an already initialized RRZE Answers 1.4.6 installation, including its
completed per-site migrations. It does not prove a direct upgrade from the former
standalone FAQ/Glossary/Synonym plugins or an installation with unfinished legacy
migrations. Those require a separate migration path; the removed conversion
routines must not simply be replayed against current placeholder data.

The runner exercises WordPress, the Gutenberg parser and REST controllers without
a browser. It does not substitute for browser, theme or keyboard-accessibility
checks. The WordPress/PHP versions are those supplied to the test command.
Warnings and notices fail the request. The known pre-init translation notice in
the old release is excluded; PHP deprecations originating in WordPress core are
reported separately to allow testing older core versions on newer PHP runtimes.

## Verified on 2026-10-02

| WordPress | PHP | Upgrade, editor save, first/repeated sync |
| --- | --- | --- |
| 6.8 | 8.5.10 | Passed on both fixture sites |
| 7.1.2 | 8.5.10 | Passed on both fixture sites |

WordPress 6.8 emitted deprecations at two core locations under PHP 8.5; these
were recorded separately. PHP 8.2 and browser/theme behavior remain unverified.
