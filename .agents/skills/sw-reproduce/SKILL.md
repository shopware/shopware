---
name: sw-reproduce
description: >
  Reproduce a Shopware 6 bug on your ALREADY-RUNNING local instance (no provisioning). Authors a
  reproduction bundle (reproduction-plan.json + optional fixtures.json + one Playwright/PHPUnit/HTTP
  test) following the same playbook as the CI reproduce workflow, runs it against the live instance
  via the shared `repro` CLI, and shows a report with the outcome, screenshots, video, and the test
  case. Single-leg only — it reports whether the bug reproduces on YOUR installed version; it does not
  provision, does not compare against trunk, and posts nothing. Use when the user asks to reproduce a
  bug locally, "does this repro on my instance", or references an issue to try against a running shop.
license: MIT
allowed-tools: Bash(node .github/actions/reproduce/cli/repro.mjs:*) Bash(gh issue view:*) Bash(npx playwright:*) Bash(npm:*) Bash(rg:*) Bash(find:*) Bash(ls:*) Bash(cat:*) Bash(jq:*) Read Write Edit Glob Grep
---

# Shopware Reproduce (local)

Reproduce a bug on the **Shopware instance you already have running** — no provisioning, no trunk
comparison. You author a reproduction bundle and run it against the live shop with the same CLI and
playbook the CI workflow uses; the only differences are: one instance (your installed version), the
DB is **not** reset, and nothing is posted anywhere.

This skill is guidance only. All executable logic is the single source in
`.github/actions/reproduce/` — you drive it, you don't reimplement it. Your role, the trust boundary,
and what makes a reproduction *faithful* are the shared `.github/aw/shared/reproduce-policy.md` — the
**same** rubric the CI workflow loads, so the two can't drift. Read it first.

## Before you start

- A Shopware instance must be **running and reachable**. Confirm the URL (default
  `http://localhost:8000`) and export it: `export APP_URL=http://localhost:8000`.
- The CLI reads the shop checkout from `SHOP_DIR` (default `shop`, or `.` if run from a Shopware
  root). Admin credentials default to `admin` / `shopware` (override with `ADMIN_USER`/`ADMIN_PASS`).
- For a visual (Playwright) repro, `@playwright/test` + a browser must be installed
  (`npm i -D @playwright/test && npx playwright install --with-deps chromium`).

## Flow

1. **Get the bug.** `gh issue view <n>` (read the body + human comments + screenshots), or have the
   user paste it. Note the **reported version**.

2. **Version check — always run this first and surface the result:**
   ```
   node .github/actions/reproduce/cli/repro.mjs version <reported-version>
   ```
   It prints the live instance version and warns if it differs from the reported one. If it warns,
   tell the user plainly: the reproduction reflects *their* installed version and may not match the
   report — offer to continue anyway.

3. **Author the bundle** following the shared policy + the same on-disk playbook: read
   `.github/aw/shared/reproduce-policy.md` (role/trust/faithfulness), then
   `.github/actions/reproduce/prompt/task.md` and the guides under
   `.github/actions/reproduce/prompt/guides/`. Write `reproduction-plan.json`, optional
   `fixtures.json`, and one test artifact (`repro.spec.ts` / `ReproTest.php`) — or an HTTP plan. Pick
   the cheapest faithful executor exactly as the playbook describes (visual ⇒ playwright).

4. **Run it against the live instance** (stop at the first failing step and report it):
   ```
   node .github/actions/reproduce/cli/repro.mjs validate      # structure + determinism gates
   node .github/actions/reproduce/cli/repro.mjs seed          # only if fixtures.json exists
   node .github/actions/reproduce/cli/repro.mjs check          # playwright readiness (if applicable)
   node .github/actions/reproduce/cli/repro.mjs try            # runs the bundle → builder-result.json + test-results/
   ```
   ⚠️ `seed`/`try` run against the **live DB with no reset** — they mutate the running instance
   (seeded rows persist). Warn the user before seeding.

5. **Report** — read `builder-result.json` (status + evidence) and `test-results/`, then present a
   summary that mirrors the CI comment (see below).

## Report format

Present it as compact Markdown in the chat (do not post anywhere). Mirror the CI comment's shape,
minus the trunk column:

- **Headline:** `Reproduced on <live version>` (status `reproduced`) or `Not reproduced on <live
  version>` (`not_reproduced`); `Inconclusive`/`Blocked` with the reason otherwise.
- **Surface:** the plan's `layer` · `executor`.
- **Result:** for HTTP, the failing check(s); for playwright/direct, the reporter output. Then, when
  present in `test-results/`: link the video (`.webm`) and embed/point to the screenshot (`.png`).
- **Test case:** the authored spec/test, and `fixtures.json` if any.
- If the version-check warned in step 2, repeat the caveat here.

## Constraints

- **Never provision and never reset** — use the instance as-is.
- **Single leg only.** You report whether it reproduces on the installed version. There is no trunk
  comparison and no automatic live_bug/fixed_on_trunk verdict — that's the CI workflow's job.
- **Local only** — never post a comment or push anything.
- **Reuse, don't reimplement** — everything runs through `.github/actions/reproduce/` (CLI,
  executors, prompt, report). If that logic needs changing, change it there so CI and this skill stay
  in lockstep.
