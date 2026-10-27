# Local Firefox rollout checks

These scripts are diagnostics for a disposable WordPress installation. They
refuse to write unless `DB_NAME` is `rrze_answers_tests` and `$wpdb->prefix` is
`rrze_rollout_browser_` (WordPress 7.1.3, port 8876) or
`rrze_rollout_browser70_` (WordPress 7.0, port 8877). Never run them against the
regular local multisite. The HTTP checker also verifies the exact loopback URL
associated with the table prefix.

The 2026-10-27 review used WordPress 7.1.3 / PHP 8.5.12 at
`http://127.0.0.1:8876`, with a separate installation under
`/private/tmp/rrze-answers-rollout-browser`. Its config uses the guarded test
database config, disables automatic cron and updates, blocks outgoing HTTP and
mail, and loads the candidate plugin from the working tree. Browser checks use
Firefox. No production content is copied into this installation.

The 2.0 follow-up also uses the official WordPress 7.0 installation under
`/private/tmp/rrze-answers-rollout-wp70`, with its own browser tables and port
8877. Both cores run on PHP 8.5.12; PHP 8.2 testing is excluded by request.
Upgrade workers isolate their MU-plugin directory so browser fixture hooks
cannot overwrite the upgrade harness's synthetic HTTP responses.

After installing this isolated site and activating the plugin, seed once:

```sh
wp eval-file tests/rollout-browser/seed.php \
  --path=/private/tmp/rrze-answers-rollout-browser
```

The seed creates all four content types, explicit-selection sentinels, all five
legacy block types, real inline-format markup, closed/open shortcode pages and
index/tab examples. A second category exercises a hidden tab, and a separate
page combines old letter fragments with `load_open`. It is intentionally not
idempotent; keep the printed IDs. Resolve category IDs with `wp term list` when
checking an old `#ID-<termId>` link rather than assuming a fixed ID.

For an isolated, disposable installation, link the available FAU themes and
Elements plugins into its `wp-content` directory. Exercise these configurations
one at a time:

| Theme | Dependency | Renderer exercised in this review |
| --- | --- | --- |
| FAU-Einrichtungen 2.6.38 | rrze-elements 1.31.7 | Core accordion and explicitly selected native fallback |
| FAU-Elemental 1.0.21 | rrze-elements-blocks 1.3.3 | Elements accordion and tabs |

The fixture's MU plugin maps option `rollout_accordion_backend` to the plugin's
accordion-backend filter; unset it to use automatic selection. This override is
test-only. `rrze-elements-blocks` 1.3.3 requires WordPress 7.0 or later, so it
does not establish a supported older WordPress combination. Version 2.0 only
supports WordPress 7.0 or later.

For FAU-Einrichtungen, initialize its synthetic `Social Media Menu` before the
first theme boot (`wp menu create 'Social Media Menu' --skip-themes`). Otherwise
this installed theme version can fail in its own first-run menu setup. This
fixture setup does not modify the theme or represent an Answers compatibility fix.

In Firefox, check closed initial state, `load_open`, no-match search, entry hash
opening, index/group targets, category tabs and keyboard toggling. In the editor,
open/save/reopen the legacy blocks and insert all five block types through the
real inserter. Set explicit selections before saving and reopening. Insert the
Synonym and Placeholder formats through the paragraph toolbar; do not substitute
a manually generated HTML string for this interaction check.

The HTTP checker exercises persisted archive settings separately from the
settings-save hook:

```sh
wp eval-file tests/rollout-browser/check-http.php \
  --path=/private/tmp/rrze-answers-rollout-browser --skip-themes --quiet
```

It requests only the fixed loopback host, never follows redirects, and restores
the original options in `finally`. It resolves the published synthetic redirect
target by its `legacy-blocks-and-inline-formats` slug. It deliberately bypasses the
option-update hook when setting up persisted state. The separate PHPUnit
`RolloutSlugChangeTest` exercises a real slug option update with a bounded
initialization counter, preventing an infinite hook loop from hanging the suite.

The browser installation and evidence are temporary. The regular multisite,
its themes, plugin options, migrations and content must remain untouched.
