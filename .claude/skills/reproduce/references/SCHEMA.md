# Output Shape

Three contracts, one per pipeline seam. Emit JSON only in wrapper-fed / CI mode —
no markdown fence, no prose.

- `analysis.json` — produced by **Analyze**, consumed by **Reproduce**. The repro plan.
- `result.json` — produced by each **Reproduce** leg (one per target).
- `repro-output.json` — produced by **Report**, merges the legs, renders the GitHub comment.

Every phase may be a stub first (emit a hand-written object that satisfies the
contract) and an agent later. Downstream phases bind to the shape, not the source.

## Analysis (`analysis.json`)

The repro plan. Picks the cheapest faithful surface and the minimal build.

```json
{
    "schema_version": "1",
    "issue": 16638,
    "layer": "service | store-api | admin-api | storefront-ui | admin-ui",
    "executor": "direct | http | playwright",
    "version": "6.6.10.0",
    "build_profile": {
        "admin_build": false,
        "storefront_build": false,
        "theme_build": false
    },
    "fixtures": {
        "demodata": false,
        "sync_payload_path": "/tmp/repro/fixtures.json"
    },
    "scenario": [
        "Given a category with at least one product visible in the Storefront sales channel",
        "When POST /store-api/product-listing/{categoryId}?p=99 (a page past the last)",
        "Then a healthy shop returns HTTP 404 with PRODUCT__LISTING_PAGE_OUT_OF_RANGE"
    ],
    "request": {
        "method": "POST",
        "path": "/store-api/checkout/cart",
        "headers": { "Content-Type": "application/json" },
        "body": "{}"
    },
    "script_path": "repro.spec.ts",
    "assertion": {
        "kind": "http_status | response_field | exception | ui_state",
        "expect": "400",
        "field": ".errors[0].code",
        "locator": "/store-api/checkout/cart"
    },
    "plugins": [{ "name": "SwagFoo", "activate": true }],
    "derived_from": "PR#16640 tests/.../MultiWarehouseTest.php",
    "confidence": 0.82,
    "blocked_reason": null,
    "needs_info": null
}
```

Rules:

- `layer` is the cheapest surface that genuinely exercises the symptom. Order:
  `service` < `store-api` / `admin-api` < `storefront-ui` / `admin-ui`. Escalate
  only when a cheaper layer cannot fire the symptom; record why in the agent's reasoning.
- `executor` follows `layer`: `service` → `direct`, `*-api` → `http`, `*-ui` → `playwright`.
- `build_profile` enables only the surface `layer` needs. `storefront_build` /
  `theme_build` are `true` only for `storefront-ui`. A `direct` or `http` plan builds neither.
- The agent does NOT choose which versions to run — the **workflow** computes that from
  `version`: two legs (reported = `version`, trunk) normally; **one leg (trunk only) when
  `version == trunk` or on a manual `workflow_dispatch` rerun** ("not on manual rerun").
  So a dispatch always runs trunk alone; use the **label/comment** trigger for both legs.
- `fixtures.sync_payload_path` seeds exactly the entities the bug needs via the admin
  sync API with `demodata: false`. Entity and field names come from the DAL schema,
  never from probing the API.
- The sync payload is a map of **sync OPERATIONS** — each key carries an
  `{entity, action, payload}` envelope, NOT a bare entity→array (the API rejects that
  with `FRAMEWORK__INVALID_SYNC_OPERATION`):

  ```json
  {
      "product": {
          "entity": "product",
          "action": "upsert",
          "payload": [{ "id": "0192f3c4a5b67890abcdef0123456789", "name": "Repro Product", "...": "..." }]
      }
  }
  ```
- Fixture payloads may contain **plain writable fields only** — never write-protected or
  computed fields (`autoIncrement`, `createdAt`/`updatedAt`, `versionId`, `childCount`,
  `ratingAverage`, `sales`, token/`accessKey` fields, ...): the sync API rejects them with
  `FRAMEWORK__WRITE_CONSTRAINT_VIOLATION` ("This field is write-protected") and the whole
  leg blocks. When unsure whether a field is writable, leave it out — defaults are fine.
- `fixtures.sync_payload` entity ids MUST be 32-char lowercase-hex Shopware UUIDs
  (e.g. `0192f3c4a5b67890abcdef0123456789`) — the admin sync API rejects non-UUID
  strings (`FRAMEWORK__WRITE_CONSTRAINT_VIOLATION`). Use `{{SC}}/{{NAV_CAT}}/{{TAX}}/
  {{CURRENCY}}` placeholders for install-specific ids; `seed.sh` resolves them.
- **A sync-valid payload is not the same as a storefront-VISIBLE one.** The sync API only
  enforces field-level write constraints; the repro then reaches the entity through the
  storefront / store-api, which applies filters the sync API does NOT — `active`, sales-channel
  `visibilities` (visibility ≥ 10 for `{{SC}}`), a navigable category (under `{{NAV_CAT}}`
  carrying the intended `cmsPageId`), a child product whose parent declares
  `configuratorSettings` for each option, and so on. A graph that seeds with HTTP 200 but omits
  such a link renders EMPTY, so the executor's precondition can't be met → the leg is
  `inconclusive` / `PRECONDITION_NOT_FOUND` — never a false `reproduced`, but a wasted run.
  Therefore, for any NESTED fixture (CMS pages, variant/configurator products, flows,
  multi-line carts) do NOT hand-write the entity graph from memory: `rg` the entity name under
  `tests/` (or reuse the fix PR's own test data) for a known-good factory/fixture and mirror
  its required links and field shapes rather than inventing config keys.
- **Executor authoring contracts live in [`references/executors/`](executors/)** — once you
  pick the layer, read the ONE file for its executor and follow it:
  - [`executors/http.md`](executors/http.md) — `request`/`requests` (assertion on the FINAL
    response), sw-context handling, install-id placeholders, false-positive guard. No
    separate script file — the executor generates `repro.sh` from the plan.
  - [`executors/playwright.md`](executors/playwright.md) — `repro.spec.ts`: version-stable
    semantic locators, precondition-vs-symptom structure, the `waitFor` (not `isVisible`/
    `expect`) and `toBeInViewport` (not `toBeVisible`) rules.
  - [`executors/direct.md`](executors/direct.md) — `ReproTest.php`: a PHPUnit integration
    test reusing the fix PR's setup; PHPUnit summary → status mapping.
- `precheck` (OPTIONAL, only meaningful with a `*-ui` primary): a cheap **http** sub-plan run
  BEFORE the expensive browser leg, so the most fixture-fragile path (the storefront render)
  can be skipped when a faithful API check already settles the leg. Shape = the http
  executor's own fields plus a `trusted` boolean — `{ "trusted": true, "request": {...},
  "assertion": {...} }` (issue/version are inherited from the parent plan). Decision rule
  (enforced by `bin/run-precheck.sh`): a **trusted + conclusive** precheck stands as the leg
  verdict and skips Playwright; an **untrusted or inconclusive** precheck is corroboration
  only and Playwright still runs. Set `trusted: true` ONLY when the precheck faithfully
  exhibits the DOCUMENTED symptom — either it is derived from the fix PR's regression test, OR
  it asserts that symptom against a real store-api/service response (the same data the UI
  renders, e.g. the category/CMS-page resolve endpoint's product-slider element). A *guessed*
  precheck must NEVER be `trusted` (it could "reproduce" a different problem → a false
  `reproduced`). Omit `precheck` entirely when no faithful API-level assertion of the symptom
  exists.
- `script_path` names the generated script (`repro.spec.ts` for playwright, `ReproTest.php`
  for direct; omit for http). `assertion.field` is a jq path used only by `response_field`;
  `assertion.locator` is a human reference to the endpoint/UI element.
- `assertion.symptom_pattern` (optional, `direct` executor, `kind: exception`): a
  distinctive extended-regex for the symptom exception's text (e.g.
  `"1116 Too many tables"`). When the PHPUnit run ERRORS and the output matches, the leg is
  classified `reproduced` — the throw IS the symptom even if it escaped the test's
  try/catch (integration-test DAL writes run indexers synchronously). Errors that do NOT
  match stay `inconclusive` (bootstrap/compile failures).
- `scenario` is a plain-English, numbered Given/When/Then list of the repro steps,
  rendered in the comment above the script so a human reads the intent first. The
  generated script (curl or spec) must comment every step (what it does + asserts).
- `assertion` is derived from the linked fix PR's regression test or an existing test
  when one exists (`derived_from`), not discovered by trial-and-error.
- `assertion.expect` is the **healthy** value (what a fixed shop returns). A leg is
  `reproduced` when `actual != expect` (symptom present) and `not_reproduced` when
  `actual == expect` (healthy). This matches running the fix PR's regression test:
  it fails on the buggy version and passes on the fixed one.
- `confidence` (0..1) measures how FAITHFULLY the plan reproduces the reported symptom —
  **not** whether a fix exists. HIGH when the symptom is deterministically assertable
  (clear expected-vs-actual, stable endpoint/field/locator) and the environment is
  reproducible in CI; LOW when it is vague, non-deterministic, environment-dependent
  (timing/network/hardware/third-party state CI can't reproduce), or the failing layer is
  unclear. A linked fix PR's regression test is the **preferred source** to derive the
  assertion from, but its absence is **neutral** — derive from the issue's symptom and stay
  confident if it is concrete. Never dock confidence merely for "no fix PR": open, unfixed
  bugs are the pipeline's primary case. Whenever `confidence < 0.7`, ALSO set
  `confidence_reason` to the faithfulness obstacle (one sentence) — never "no fix PR"; it is
  surfaced to the human instead of a bare number. Two bands gate behaviour:
  - **`confidence < 0.4`** → the run is **not executed**. The matrix step posts the draft
    scenario + `confidence_reason` and asks a human to confirm before spending provision
    budget (provisioning two installs to test a guess is the wasteful case; below 0.4 there
    is usually no regression test to validate the legs against anyway). Terminal, like
    `needs_info`.
  - **`0.4 ≤ confidence < 0.7`** (or no faithful layer → `blocked_reason`) → the legs DO
    run, but the verdict is forced to `needs_human_review`: the evidence is shown, the
    definitive action (labels/attribution) is withheld, and `confidence_reason` is rendered
    so the human adjudicates from real leg output.
- **`needs_info`**: when the issue is too vague/contradictory/incomplete to derive a
  FAITHFUL plan, emit ONLY `{schema_version, issue, needs_info: "<one specific question>"}`
  and omit the plan. The workflow posts the question and aborts — no provisioning. (A
  cheaper deterministic version runs first in `gate`: a missing "How to reproduce" /
  "Steps to reproduce" section is rejected before any agent runs.) `needs_info` is a terminal state, like
  `blocked`/`needs_human_review`.

## Repro Result (`result.json`)

One object per Reproduce leg.

```json
{
    "schema_version": "1",
    "issue": 16638,
    "target": "reported | trunk",
    "version": "6.6.10.0",
    "executor": "playwright",
    "status": "reproduced | not_reproduced | blocked | inconclusive",
    "assertion": { "expect": "400", "actual": "200", "matched": false },
    "duration_s": 47,
    "evidence": {
        "script": "import { test, expect } from '@playwright/test';\n…",
        "script_lang": "ts | php | sh",
        "reporter_output": "✘ checkout › cart returns 400\n  Expected 400, received 200",
        "http": [{ "method": "POST", "path": "/store-api/checkout/cart", "status": 200 }],
        "artifacts": [
            { "kind": "trace | video | screenshot | html_report | har", "name": "trace.zip", "run_artifact": "repro-reported" }
        ],
        "truncated": false
    },
    "blocked_reason": null
}
```

Rules:

- `status` is one-shot and bounded. `not_reproduced` only after a single re-check.
  `blocked` when the env is dead — plugin install or theme build failed after one
  rebuild; never grind. `inconclusive` = env READY but the fixture could not be triggered.
- `evidence.script` is the full generated repro source, verbatim, always inline — the
  report stays self-contained after artifacts expire.
- `evidence.reporter_output` is the trimmed console reporter (Playwright `list`, PHPUnit,
  or the curl exchange). `evidence.http` carries the request/response (HAR) for
  `http` and `playwright` legs.
- `evidence.artifacts` are run-artifact references only and may expire. `screenshot`,
  `video`, and `trace` are emitted only by the `playwright` executor — never force a
  browser screenshot on a `direct` or `http` leg.
- The verdict in `status` / `assertion` never depends on fetching an artifact.
- Set `truncated: true` and trim `reporter_output` first when the rendered comment would
  exceed GitHub's 65 535-character limit; trim `script` last.
- Redact secrets, tokens, and instance hostnames to `[REDACTED_KEY]`, `[REDACTED_ID]`,
  `[REDACTED_URL]` before emit.

## Merged Report (`repro-output.json`)

```json
{
    "schema_version": "1",
    "issue": 16638,
    "verdict": "live_bug | fixed_on_trunk | regression | not_reproducible | blocked | needs_human_review",
    "fix_candidate": "PR#16575 (backport candidate; from analyze derived_from when fixed_on_trunk)",
    "layer": "store-api",
    "results": { "reported": { "...": "result.json" }, "trunk": { "...": "result.json" } },
    "summary": "1-3 sentences naming the symptom and the surface it fired on.",
    "label": "ci:reproduced | ci:not-reproduced | ci:fixed-on-trunk | ci:repro-blocked",
    "requires_human": false
}
```

Verdict map (first match wins, top to bottom):

| reported         | trunk            | verdict              |
| ---------------- | ---------------- | -------------------- |
| any `blocked`    | —                | `blocked`            |
| analyze `blocked_reason` set or `0.4 ≤ confidence < 0.7` | — | `needs_human_review` |
| any `inconclusive` | —              | `needs_human_review` |
| `reproduced`     | `reproduced`     | `live_bug`           |
| `reproduced`     | `not_reproduced` | `fixed_on_trunk`     |
| `not_reproduced` | `reproduced`     | `regression`         |
| `not_reproduced` | `not_reproduced` | `not_reproducible`   |
| anything else    | —                | `needs_human_review` |

Notes:

- `needs_human_review` is **deliberate**, not a catch-all: it fires when the plan is
  untrustworthy (`blocked_reason`/low confidence) or a leg is `inconclusive`. The
  trailing row is a logged safety net.
- `regression` carries a false-negative caveat: a `not_reproduced` reported leg may
  have under-exercised the symptom (e.g. missing fixture). Surface the caveat.
- `fix_candidate` is set for `fixed_on_trunk` from analyze's `derived_from` (the fix
  PR). When absent, the `attribute` phase finds the fixing/introducing commit.

Rules:

- When `targets` collapsed to one leg, the missing leg is `null`. A single-leg run can
  only yield `live_bug` (trunk reproduced), `not_reproducible`, or `needs_human_review`.
- `label` and `summary` are the only fields Report turns into write-actions. The comment
  body embeds each leg's `evidence.script` and trimmed `reporter_output`, and links
  `evidence.artifacts`.
- `requires_human` is `true` for `blocked` and `needs_human_review`.
