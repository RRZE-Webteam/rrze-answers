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

For the rollout comparison against the current main baseline, select its pinned
commit explicitly. The default remains v1.4.6; `main` always resolves to
`3b458c812ba61c1f9b8beac22022cfd7b159ba58` (1.4.7), not a moving Git branch. Both
baselines share the checked-in block attribute schemas.

```sh
RRZE_UPGRADE_REFERENCE=main RRZE_UPGRADE_ROLLBACK=1 composer test:upgrade
```

To distinguish synchronization compatibility from editor serialization, run a
separate freshly seeded scenario without opening or saving content:

```sh
RRZE_UPGRADE_REFERENCE=main RRZE_UPGRADE_ROLLBACK=1 RRZE_UPGRADE_SYNC_ONLY=1 composer test:upgrade
```

This skips editor serialization and REST saves, then tests first/repeated sync
and rollback on the original legacy block markup. Both scenarios include
unselected synonym/placeholder sentinels so a fallback to all entries cannot
silently pass when an old version discards newly typed ID attributes.

With rollback enabled, separate PHP requests load the old plugin on the persisted
database before editor saves, after editor saves and after first/repeated sync.
Each checkpoint boots both fixture sites. The checks collect all missing content,
widened selections, rendering errors and differences from the original legacy
output. They also require startup/rendering to leave the captured data unchanged.
Completed migration flags are set on empty fixture sites before inserting current
placeholder content; rollback never reruns migration functions explicitly.

Rollback failures do not prevent later forward checks and rollback checkpoints.
They do cause a nonzero final exit code. Every executed phase has a log and a
status in `results.json`; PHP request JSON includes outputs, errors and persisted
snapshots. All rollback runs retain the printed diagnostics directory. Set
`RRZE_UPGRADE_KEEP_DIAGNOSTICS=1` to retain a successful forward-only run as well.

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
The harness forces the native accordion backend so results remain comparable
across core versions. Real Elements/Core backends need separate integration and
browser checks; passing this harness does not verify their behavior.

## Verified on 2026-10-02

| WordPress | PHP | Upgrade, editor save, first/repeated sync |
| --- | --- | --- |
| 6.8 | 8.5.10 | Passed on both fixture sites |
| 7.1.2 | 8.5.10 | Passed on both fixture sites |

WordPress 6.8 emitted deprecations at two core locations under PHP 8.5; these
were recorded separately. PHP 8.2 and browser/theme behavior remain unverified.

## Rollout comparison verified on 2026-10-27

All scenarios used two freshly seeded, already-migrated sites and PHP 8.5.12.
External HTTP remained blocked; synchronization used the fixture responses.

| Baseline | WordPress | Forward upgrade, save and sync | Rollback before saves | Rollback after saves | Independent sync-only rollback |
| --- | --- | --- | --- | --- | --- |
| main (`3b458c8`, 1.4.7) | 7.1.3 | Passed | Passed | Failed | Passed |
| main (`3b458c8`, 1.4.7) | 6.8 | Passed | Passed | Failed | Passed |
| v1.4.6 | 7.1.3 | Passed | Not run | Not run | Not run |

After current-editor saves, main discarded incompatible typed attributes and
broadened FAQ, glossary, synonym and placeholder selections. The FAQ widget also
changed from flat output with a hidden title to an accordion. Classic shortcodes
and inline formats retained their output. No captured database records changed
during rollback startup or rendering. A separate seed → sync → rollback run
passed on both core versions, isolating the failure to editor serialization.
These results do not establish compatibility with PHP 8.2 or any browser/backend
combination beyond the native server-side output exercised here.
