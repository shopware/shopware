# End-to-end harness

Drives the **full reproduce pipeline against a real live shop** with a scripted fake agent (no LLM,
no cost) standing in for the model — all the way to a verdict + comment — and asserts the verdict
matches each scenario's `expected.json`. This is the "red field" coverage the unit suite can't give:
real live-shop executors, sandbox arming, and the executor → `verify` → `verdict` → `comment` spine.

## Pieces

- `drive.sh` — the harness: fake agent → `validate` → `verify` (reported) → `verify` (trunk) →
  `verdict` → `comment` → assert. Both legs run against the **one** instance, so verdicts are
  `live_bug` / `not_reproducible`; the true two-version differential stays unit-level
  (`report/verdict.test.ts`).
- `agents/replay.sh` — the fake agent: real read-only `repro schema`/`search` calls + `repro try`
  against the live shop, then copies the scenario's canned bundle into the workspace.
- `scenarios/<name>/` — a canned bundle (`reproduction-plan.json` [+ `fixtures.json` / `ReproTest.php`
  / `repro.spec.ts`]) plus `expected.json` (`{verdict, reported, trunk, commentIncludes, notes}`).

## Running locally (against a running shop)

```bash
# http (needs only APP_URL + a store-api access key):
APP_URL=http://localhost:8000 SW_ACCESS_KEY=SWSC… \
  npm run test:e2e -- healthy-store-api reproduced-store-api

# direct / playwright (need docker; direct also SHOP_DIR + a reachable DB):
APP_URL=http://localhost:8000 SW_ACCESS_KEY=… SHOP_DIR=~/dev/shopware \
  ADMIN_USER=admin ADMIN_PASS=shopware \
  npm run test:e2e -- reproduced-direct healthy-storefront
```

Run against a **disposable/dev instance**, not production data: seeding scenarios mutate the DB (the
harness snapshots + resets around a run when a snapshot is available).

## The two deliberate local↔CI differences

1. **Light arm (local) vs full arm (CI).** `execute-bundle` fails closed on a direct/playwright
   `verify` without `REPRO_SANDBOX_ARMED` (containment for *untrusted agent code*). Our scenario
   bundles are checked-in and trusted, so `drive.sh` builds/pulls the same image and sets `ARMED` but
   **skips the iptables egress DROP** locally (needs sudo, and the containment is moot for trusted
   code). CI passes `--lockdown` and pre-arms fully (`reproduce-e2e.yml`); `arm_for` respects a
   pre-armed env. If `execute-bundle`'s gate is ever hardened to *verify* egress is actually dropped,
   revisit this.
2. **Same-instance both legs** — see above.

## What's validated vs. pending

- **http tier** (`healthy-store-api`, `reproduced-store-api`) — validated locally against a mock shop.
- **direct / playwright** (`reproduced-direct`, `healthy-storefront`) — bundles pass `repro validate`;
  the live run is first exercised in CI (`reproduce-e2e.yml`) or against a local instance.

## CI

`.github/workflows/reproduce-e2e.yml` provisions a real Shopware (`setup-shopware`, trunk) and runs
all scenarios. Path-filtered on PRs (our regressions), nightly (external drift), + manual dispatch.
