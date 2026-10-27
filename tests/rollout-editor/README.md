# Local editor rollout checks

These diagnostics exercise forward-upgrade criteria against `main` at commit
`3b458c812ba61c1f9b8beac22022cfd7b159ba58` and the candidate's committed JavaScript
bundles. They intentionally fail when a compatibility criterion is not met; they
do not change product code or the local WordPress DB. Set `RRZE_ROLLOUT_MAIN_REF`
to another explicit Git reference only when reviewing a different baseline.

```sh
RRZE_ROLLOUT_WP70_SSR=/path/to/wordpress-7.0/wp-includes/js/dist/server-side-render.js \
npm run test:unit -- --runInBand --watch=false tests/js/rollout-editor-compatibility.test.js

node tests/rollout-editor/check-build.js
```

Use an official WordPress 7.0 installation for `RRZE_ROLLOUT_WP70_SSR`. The core
asset is evaluated unchanged, then the actual checked-in plugin bundles are
loaded. UI controls, hooks and JSX are shallow test doubles; this verifies the
export/component contract, not browser interaction or a complete core runtime.
Without that environment variable, the minimum-core tests are explicitly skipped.

Upgrade tests serialize content using main's original schema, load it with the
candidate's Gutenberg registration, save it, and reopen it using the 2.x schema.
A code-only downgrade after editor saves is not a release criterion for 2.0.
The opt-in PHP rollback diagnostic remains available to inspect old behavior;
the historical audit report records why it is unsupported. Existing
inline-format tests compare both versions' compiled format registrations and run
saved HTML through the real installed Gutenberg rich-text parser and serializer.
Toolbar insertion, update/removal and cursor behavior still require browser tests.

The build helper copies tracked working files into a private temporary directory,
uses the existing dependency installation, builds there, and compares all build
artifacts byte-for-byte. The temporary build, stdout log and `comparison.json`
remain available at the printed path for inspection. An unsuccessful build also
preserves its stderr log. A mismatch produces a non-zero exit status.
