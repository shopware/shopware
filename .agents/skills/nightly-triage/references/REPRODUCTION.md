# Local Reproduction (Docker, major mode)

How the `integration-major` job runs
(`.github/workflows/integration-major.yml`): `APP_ENV=test`,
`FEATURE_ALL=major`, `BLUE_GREEN_DEPLOYMENT=1`, fresh install per shard.

Reproduce failures against the local Docker stack (`docker compose exec web`).
Ask before the first reproduction run — it mutates the shared test DB.

## Two-step escalation

**Step A — runtime flags only (cheap, try first):**

```bash
docker compose exec -e APP_ENV=test -e FEATURE_ALL=major -e BLUE_GREEN_DEPLOYMENT=1 \
  web php -d memory_limit=2G vendor/bin/phpunit --testsuite integration \
  --filter 'ClassTest::testMethod'
```

Feature-flag checks (`Feature::isActive('v6.8.0.0')`) are runtime — most
major breakage (Required fields, deprecation enforcement, pipeline switches)
reproduces this way against the normal test schema.

- **Fails with the CI signature** → mechanism confirmed.
- **Passes** → the CI failure is *schema-dependent* (major migrations) → Step B,
  or mark "mechanism TBD" if Step B is not warranted.

**Step B — full major DB (expensive):** add `-e FORCE_INSTALL=true` to the
Step A command. Rebuilds the test schema with major migrations. Reserve for
clusters where Step A passed but you need the mechanism.

## Sampling rule

Run the test variant with the **error** (e.g. the WriteException one), not an
assertion-failure sibling — siblings can pass locally while the error variant
fails, and they can have different causes. One test per cluster is enough;
two if the first result surprises you.

## Traps

- **SwagCommercial B2B bootstrap crash**: `Table
  'shopware_test.b2b_components_individual_pricing' doesn't exist` during
  `RefreshIndexCommand`. Cause: the commercial bundle loads from
  `var/plugins.json` regardless of DB state, so a freshly-recreated DB lacks
  its tables while its indexers are registered. If `FORCE_INSTALL` crashes
  this way, rerun — the plugin installs after system-install on the retry
  (`composer init:testdb` with `FORCE_INSTALL=true` recovers reliably).
- **A `--filter` run can drag in extra tests** (e.g. a data-provider error in
  an unrelated class whose provider touches deprecated API). Read the output
  by test name, not by exit code.
- **Foreground, full output.** Run phpunit in the foreground with a generous
  timeout; don't truncate with `| tail` when the user needs to see it.

## Restore (mandatory after any major-mode run)

```bash
docker compose exec -e APP_ENV=test -e FORCE_INSTALL=true web composer init:testdb
```

(Plain `init:testdb` without `FORCE_INSTALL` can hit the B2B trap above.)
Then verify with a probe: rerun one test that failed under major flags — it
must pass now:

```bash
docker compose exec -e APP_ENV=test web php -d memory_limit=2G \
  vendor/bin/phpunit --testsuite integration --filter 'ClassTest::testMethod'
```
