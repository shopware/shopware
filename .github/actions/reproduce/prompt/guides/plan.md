# `reproduction-plan.json` reference

The deterministic handoff. `repro validate` enforces the required parts; everything else is guidance.

```json
{
  "schema_version": "1",
  "issue": 16599,
  "executor": "playwright",              // playwright | http | direct
  "layer": "storefront-ui",              // storefront-ui | admin-ui | store-api | admin-api | service
  "version": "6.6.10.0",                 // reported version (given in context.md)
  "build_profile": { "admin_build": false, "storefront_build": true },
  "browser_state": { "auto_cookie_consent": true },
  "record_video": false,                 // playwright: true → each leg records a .webm (motion/interaction bugs)
  "viewport": { "width": 390, "height": 844 }, // playwright: OMIT for desktop; set for mobile/responsive bugs
  "fixtures": { "demodata": false },     // set demodata:true only if realistic catalog volume is needed
  "seeded_readiness": [                  // playwright + fixtures: prove the seeded state renders
    { "kind": "browser", "path": "/detail/<id>", "selector": ".product-detail-name", "text": "Seeded Product" }
  ],
  "scenario": ["Given …", "When …", "Then …"],   // one step per entry; rendered in the comment
  "script_path": "repro.spec.ts",        // playwright/direct artifact path (default per executor)
  "confidence": 0.8,                     // < 0.7 routes the verdict to needs_human_review
  "agent_explanation": null,             // one sentence ONLY if a fixture/setup limitation affected authoring
  "derived_from": null,                  // keep null — do not research fix history
  "blocked_reason": null                 // set only for an external blocker, never "setup not written yet"
}
```

## Field notes

- **executor / layer** must be consistent: any spec that navigates to `/admin…` is `admin-ui` and
  should set `build_profile.admin_build: true`. http/direct normally leave build flags false.
- **build_profile** tells the trunk leg what to build — only what your repro needs, so trunk provisions fast.
- **browser_state.auto_cookie_consent** — leave `true` for ordinary Storefront bugs (the harness
  pre-accepts consent). Set `false` only when the bug *is* the cookie/consent flow.
- **record_video** — optional, playwright only. `true` records a `.webm` of each leg for the comment;
  use it for motion/interaction bugs a screenshot can't convey (animation, drag, toggle, a control
  that won't respond). When you enable it, **narrate the spec** so the clip is followable — see
  [playwright-narration.md](playwright-narration.md).
- **viewport** — optional, playwright only. Omit for desktop bugs. Set `{ "width", "height" }` for a
  mobile/responsive/off-canvas symptom: the harness applies it at context creation so both legs run —
  and the video records — at that size. Never resize with `page.setViewportSize()` in the spec.
- **seeded_readiness** — required when a playwright repro uses `fixtures.json`. It proves SETUP, not
  the symptom: use a stable seeded-identity marker (product title/number, a container), never the
  reported broken control. Details in [fixtures.md](fixtures.md).
- **scenario** — Given/When/Then, one per array entry. Keep it the reader's context for the verdict.
- **confidence** — honest 0–1. Below 0.7 the report is marked needs_human_review.

## http plan extras

Put the request(s) and assertion(s) in the plan itself (no separate artifact):

```json
{
  "executor": "http", "layer": "store-api", "version": "6.6.10.0", "issue": 1,
  "requests": [ { "method": "POST", "path": "/store-api/product/0af1e2c3d4e5f60718293a4b5c6d7e8f", "body": { "salutationId": "{{SALUTATION}}" } } ],
  "assertions": [
    { "kind": "http_status", "expect": 200, "role": "precondition", "label": "product loads" },
    { "field": ".product.calculatedPrice.unitPrice", "op": "equals", "expect": 19.99, "role": "assert", "label": "price is correct" }
  ]
}
```

Any id in the path/body/assertions that references an existing install entity must be a
`{{PLACEHOLDER}}`, never a literal install id — the plan is replayed on trunk where those ids differ.
There is no product placeholder: seed the product in `fixtures.json` with a stable id and reference
that id (as above). See [fixtures.md](fixtures.md) for the resolver's placeholder list and seeding,
and [executors.md](executors.md) for the id rule, the assertion operators, and how each status is
decided.
