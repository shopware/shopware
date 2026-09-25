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

- A Shopware instance must be **running and reachable**. Confirm the URL (default `http://localhost:8000`).
- Know your **shop checkout path** — the repo root if you're in a Shopware checkout, else the shop's
  absolute path. Admin creds default to `admin` / `shopware` (override `ADMIN_USER` / `ADMIN_PASS`).
- For a visual (Playwright) repro, `@playwright/test` + a browser must be installed
  (`npm i -D @playwright/test && npx playwright install --with-deps chromium`).
- **Working directory (gitignored).** The CLI writes the bundle + all artifacts as bare filenames
  into the current directory, so run everything from `var/sw-reproduction/`. `var/` is Shopware's
  runtime scratch dir: already gitignored (`.gitignore` → `/var/`), present in every checkout, and
  swept by the cleanup devs/CI already run — so the bundle stays out of your working tree and out of
  any session-temp path. Set up once:
  ```
  ROOT="$(git rev-parse --show-toplevel 2>/dev/null || pwd)"; SHOP="${SHOP_DIR:-$ROOT}"; APP="${APP_URL:-http://localhost:8000}"
  WORK="$ROOT/var/sw-reproduction"; mkdir -p "$WORK"
  ```
  Below, **`repro <cmd>`** means run the CLI from that dir:
  ```
  ( cd "$WORK" && SHOP_DIR="$SHOP" APP_URL="$APP" node "$ROOT/.github/actions/reproduce/cli/repro.mjs" <cmd> )
  ```
  Author the bundle files **inside `var/sw-reproduction/`** too, so everything — `reproduction-plan.json`,
  the test artifact, `builder-result.json`, `test-results/`, screenshots, video — stays there.

## Flow

1. **Get the bug.** `gh issue view <n>` (read the body + human comments + screenshots), or have the
   user paste it. Note the **reported version**. If your environment lets you name the
   conversation/session, title it `<3–5 word issue subject> #<number> Reproduction`
   (e.g. `App Media Action Buttons #123 Reproduction`) so the run is identifiable at a glance without
   opening it — derive the subject from the issue title, not the bare number.

2. **Version check — always run first and surface the result:** `repro version <reported-version>`.
   It prints the live instance version and warns if it differs. If it warns, tell the user plainly:
   the reproduction reflects *their* installed version and may not match the report — offer to
   continue anyway.

3. **Author the bundle** (in `var/sw-reproduction/`) following the shared policy + the on-disk playbook: read
   `.github/aw/shared/reproduce-policy.md` (role/trust/faithfulness), then
   `.github/actions/reproduce/prompt/task.md` and its `guides/`. Write `reproduction-plan.json`,
   optional `fixtures.json`, and one test artifact (`repro.spec.ts` / `ReproTest.php`) — or an HTTP
   plan. Pick the cheapest faithful executor as the playbook says (visual ⇒ playwright).

4. **Run it against the live instance** (stop at the first failing step and report it):
   `repro validate` → `repro seed` (only if `fixtures.json`) → `repro check` (playwright readiness) →
   `repro try` (runs the bundle → `builder-result.json` + `test-results/`).
   ⚠️ `seed`/`try` hit the **live DB with no reset** — they mutate the running instance (seeded rows
   persist). Warn the user before seeding.

5. **Report** — read `var/sw-reproduction/builder-result.json` + `var/sw-reproduction/test-results/`, then present the
   report below.

## Report format

Present compact Markdown in the chat (post nothing). Mirror the CI verdict comment, minus the trunk
column:

- **Headline:** `Reproduced on <live version>` (`reproduced`) / `Not reproduced on <live version>`
  (`not_reproduced`); else `Inconclusive` / `Blocked` with the reason (from `builder-result.json`).
- **Surface:** the plan's `layer` · `executor`.
- **Result:** http → the failing check(s); playwright/direct → the reporter output.
- **Evidence (playwright):** **Read the screenshot** (`var/sw-reproduction/test-results/…png`) so you can see
  and describe the symptom, and give the user a **clickable link** to both the screenshot and the
  video (`var/sw-reproduction/test-results/…webm`). Inline the image when the client renders it; a clickable
  link is the minimum.
- **Test case — always inline it.** Show the full authored test in a fenced block (```ts for
  `repro.spec.ts`, ```php for `ReproTest.php`, or the request + assertions for an HTTP plan), plus
  `fixtures.json` if present. Seeing exactly how it was tested is the point — same as the CI comment.
- If the version-check warned in step 2, repeat the caveat here.

## Constraints

- **Never provision and never reset** — use the instance as-is.
- **Single leg only.** You report whether it reproduces on the installed version. There is no trunk
  comparison and no automatic live_bug/fixed_on_trunk verdict — that's the CI workflow's job.
- **Local only** — never post a comment or push anything.
- **Reuse, don't reimplement** — everything runs through `.github/actions/reproduce/` (CLI,
  executors, prompt, report). If that logic needs changing, change it there so CI and this skill stay
  in lockstep.
