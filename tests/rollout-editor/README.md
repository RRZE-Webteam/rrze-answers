# Local editor rollout checks

These diagnostics exercise release criteria against `main` at commit
`3b458c812ba61c1f9b8beac22022cfd7b159ba58` and the candidate's committed JavaScript
bundles. They intentionally fail when a compatibility criterion is not met; they
do not change product code or the local WordPress DB. Set `RRZE_ROLLOUT_MAIN_REF`
to another explicit Git reference only when reviewing a different baseline.

```sh
RRZE_ROLLOUT_WP68_SSR=/path/to/wordpress-6.8/wp-includes/js/dist/server-side-render.js \
npm run test:unit -- --runInBand --watch=false tests/js/rollout-editor-compatibility.test.js

node tests/rollout-editor/check-build.js
```

Use an official WordPress 6.8 installation for `RRZE_ROLLOUT_WP68_SSR`. The core
asset is evaluated unchanged, then the actual checked-in plugin bundles are
loaded. UI controls, hooks and JSX are shallow test doubles; this verifies the
export/component contract, not browser interaction or a complete core runtime.
Without that environment variable, the minimum-core tests are explicitly skipped.

Rollback tests parse main-compatible content with the candidate's Gutenberg
registration, serialize it, and reopen it using main's original schema. Existing
inline-format tests compare both versions' compiled format registrations and run
saved HTML through the real installed Gutenberg rich-text parser and serializer.
Toolbar insertion, update/removal and cursor behavior still require browser tests.

The build helper copies tracked working files into a private temporary directory,
uses the existing dependency installation, builds there, and compares all build
artifacts byte-for-byte. The temporary build, stdout log and `comparison.json`
remain available at the printed path for inspection. An unsuccessful build also
preserves its stderr log. A mismatch produces a non-zero exit status.
