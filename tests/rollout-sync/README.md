# Local rollout comparison against main

The result tables below preserve the pre-fix audit baseline. See the
[2.0 validation follow-up](../RELEASE-2.0-VALIDATION.md) for the corrected
candidate and the consumer-first rollout requirement.

These tests use the guarded, separate WordPress test database described in
`tests/README.md`. They recreate the `wptests_` tables. Run the commands
sequentially and never concurrently with another PHPUnit run on that database.
No production database, external HTTP endpoint or existing site is required.

The baseline reviewed on 2026-10-27 is main commit
`3b458c812ba61c1f9b8beac22022cfd7b159ba58`. The candidate is the current working
tree. The baseline bootstrap loads the archived plugin's classes, not current
classes under old fixtures. It suppresses only main's known
`_load_textdomain_just_in_time` notice for `rrze-answers`, which otherwise blocks
PHPUnit's isolated child processes before their assertions execute.

## Reproduce

Run these commands from the plugin root with Composer dependencies installed.
Keep the temporary directory to inspect the captured synthetic REST responses.
Test failures intentionally return nonzero; continue through all matrix cells.

```sh
rollout_dir="$(mktemp -d /tmp/rrze-answers-rollout.XXXXXX)"
mkdir "$rollout_dir/main" "$rollout_dir/dev-wire" "$rollout_dir/main-wire"
git archive --format=tar --output="$rollout_dir/main.tar" 3b458c812ba61c1f9b8beac22022cfd7b159ba58
tar -xf "$rollout_dir/main.tar" -C "$rollout_dir/main"

# Candidate probes, plus candidate producer -> candidate consumer.
RRZE_ROLLOUT_WIRE_CAPTURE_DIR="$rollout_dir/dev-wire" \
composer test -- --filter 'Rollout(CronSync|RESTAccess|MultisiteCache|WireCompatibility|SlugChange)Test'

# Identical probes on main, plus main producer -> main consumer.
RRZE_ROLLOUT_MAIN_DIR="$rollout_dir/main" \
RRZE_ROLLOUT_WIRE_CAPTURE_DIR="$rollout_dir/main-wire" \
composer test -- --bootstrap tests/rollout-sync/bootstrap-main.php \
  --filter 'Rollout(CronSync|RESTAccess|MultisiteCache|WireCompatibility|SlugChange)Test'

# Candidate producer -> main consumer.
RRZE_ROLLOUT_MAIN_DIR="$rollout_dir/main" \
RRZE_ROLLOUT_WIRE_CONSUME_DIR="$rollout_dir/dev-wire" \
composer test -- --bootstrap tests/rollout-sync/bootstrap-main.php \
  --filter RolloutWireCompatibilityTest

# Main producer -> candidate consumer.
RRZE_ROLLOUT_WIRE_CONSUME_DIR="$rollout_dir/main-wire" \
composer test -- --filter RolloutWireCompatibilityTest

# Finish by checking the complete candidate suite.
composer test
```

## What the probes establish

- `RolloutCronSyncTest`: FAQ/glossary insert and update with no logged-in user,
  including actual category/tag relationships, not merely updated post content.
  It calls the synchronization boundary directly; cron scheduling/logging is
  outside this probe.
- `RolloutRESTAccessTest`: anonymous FAQ/glossary export enabled and disabled,
  including `check_authentication()`, which `rest_do_request()` alone bypasses.
- `RolloutMultisiteCacheTest`: both directions of `switch_to_blog()` with
  different synchronization states, avoiding cache keys used by earlier tests.
- `RolloutWireCompatibilityTest`: real REST controller responses from a second
  synthetic source blog, replayed through `pre_http_request`. Plain, embedded
  and taxonomy responses match what each consumer requests. This checks the
  data contract; it does not exercise a real HTTP server or proxy.
  Destination distractor terms and explicit ID inequality assertions make the
  source/destination distinction observable. A repeated import must retain its
  ID without duplicates; a second real source snapshot renames both terms and
  advances `remoteChanged`, requiring updated destination relationships.
- `RolloutSlugChangeTest`: one settings update changing an existing FAQ slug,
  bounded before a twenty-first `init` cycle. Known duplicate-block diagnostics
  are expected; filesystem rewrite writes are disabled. This guards against
  hangs without treating a re-entering test harness as a product defect.

The assertions describe correct behavior and deliberately remain red when a
defect is present. Compare both plugin generations before labeling a failure
as a regression. No product implementation is modified by this test work.

## Results on 2026-10-27

Verified locally with PHP 8.5.12 and WordPress 7.1.3. The complete candidate
PHPUnit suite ran 146 tests with 726 assertions: 140 passed and 6 failed, with
no errors or skips. The selected baseline suite ran 13 tests with 145
assertions: 7 passed and 6 failed.

| Probe | main | Candidate | Classification |
| --- | --- | --- | --- |
| Anonymous insert/update taxonomy assignments | 4 failures | 4 failures | Existing baseline defect |
| Multisite synchronization-state cache | 2 passes | 2 failures | Candidate regression |
| Anonymous REST export on/off | 4 passes | 4 passes | Compatible |
| Bounded existing-slug update | Pass | Pass | No reproduced product hang |

| Producer | Consumer | FAQ / glossary |
| --- | --- | --- |
| main | main | Both fail: source IDs are resolved against destination terms |
| Candidate | main | Both fail for the same existing consumer defect |
| main | Candidate | Both pass, including repetition and changed taxonomy names |
| Candidate | Candidate | Both pass, including repetition and changed taxonomy names |

The old consumer either assigns an unrelated destination distractor term or
leaves relationships empty. This is observable before upgrading its producer;
it is not a new producer-side wire incompatibility. Cron failures likewise
exist on main: post content is updated, while taxonomy relationships are absent
on insertion or remain unchanged on update. The candidate's `hasSync()` cache
failure is the newly introduced multisite difference established by these probes.

Term-only renames without a newer `remoteChanged` remain outside this update
scenario. The successful wire cells use an administrator for persistence;
the separate anonymous-write probes expose the cron capability defect.
