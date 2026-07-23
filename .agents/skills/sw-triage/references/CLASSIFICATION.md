# Disposition, Severity & Confidence Calibration

## Disposition taxonomy

Exactly one of:

- **`valid-bug`** — Defect with a clear understanding of what's broken. The actual_behaviour is unambiguous. Minor template gaps are FINE if the defect itself is clear. **Default to `valid-bug` for any plausible defect where you can describe the problem in your own words.**
- **`duplicate`** — Same defect as an already-tracked issue. Set `duplicate_of` to the issue number. Only assert if symptoms genuinely match.
- **`needs-info`** — Issue is **fundamentally unclear**: the defect cannot be understood from the input. Empty/vague actual_behaviour, off-topic, gibberish, or repro steps insufficient to reproduce. **Do NOT use just because a template field is short or has a placeholder (`.` / `_No response_`)** — only when you genuinely cannot tell what the bug is.
- **`not-a-bug`** — Working-as-designed, config question, third-party plugin issue, support request misfiled as bug, or **product/user-centric request** that belongs in the Shopware Feedback & Ideas portal rather than as a code change.
- **`feature-request`** — Describes a desired capability or **technical improvement / refactor** disguised as a bug. Use this for technical change requests; use `not-a-bug` for end-user product feedback.

**Heuristik:** Try to describe the defect in one sentence. If you can, it's `valid-bug` (or `duplicate`). If you can't, it's `needs-info`. If it's a desired change rather than a broken behaviour, it's `feature-request` (technical) or `not-a-bug` (product/UX wish).

## Severity rubric

Severity reflects **impact × probability**: how broken is the system AND how many merchants are realistically affected? Shopware uses separate `priority/*` labels for business urgency — do not invent those.

Exactly one of:

### `critical`

Immediate, direct impact on customers / partners / Shopware itself. Includes data loss, security, legal, large revenue/cost impact, or a flagship feature losing its main functionality.

Concrete Shopware patterns that are usually `critical`:

- CRUD of products, orders, customers, categories broken
- General checkout broken (payment or shipping methods)
- Extendability broken (plugins / apps no longer work)
- Commercial features usable without a valid license
- Storefront or Admin no longer reachable
- Install / Update fails (incl. extension install/update)
- Flow Builder trigger no longer fires
- Major performance regression in cart / checkout / product listing / storefront registration / admin core usage

### `high`

Module / feature set still provides its main function, but a meaningful aspect is broken — efficiency or productivity loss, especially in differentiator features (Rule Builder, Flow Builder, Digital Sales Rooms).

Concrete patterns:

- Bulk edit can't edit specific values / entities
- Flow Builder ignores delays or fires them immediately
- Import/Export breaks Multi-Inventory product import
- Paypal Express button missing under common configurations
- Custom Products advanced surcharges broken
- Visual regressions (treat as `high` by default — small individually, but they accumulate and erode polish quickly)

### `medium`

Defect with a practical workaround. Affects a subset of merchants or only specific configurations. The functionality still works in the common case.

### `low`

Cosmetic, edge case, minor UX gap, non-default config, easy manual workaround. The main feature is unaffected; the impact is "slightly worse experience" rather than "broken".

Concrete patterns:

- Cover image of a duplicated product removed when the original is deleted
- Long category names truncate weirdly in the admin tree
- SEO URL property set only once when the same product is in multiple cross-selling groups
- Error message thrown N times instead of once
- "Unsaved changes" modal appears with no actual changes

## Severity = impact × probability

The two factors are independent — neither alone is enough.

- **High impact + low probability** (only on outdated hardware / extreme config / theoretical) → step down one level.
- **Low impact + universal reach** (every merchant sees it) → step up one level.
- **Major performance issue** that affects everyday hot paths (cart / admin login / product list) is `critical` even if the technical defect is small.

**Edge case — prestige customers:** a single major customer / strategic project hitting a `high`-impact bug can justify `critical`. The issue reporter is rarely visible to you; lean on the bug's intrinsic severity and call this out in `reasoning` when the input suggests it.

**Default to the LOWER severity when uncertain.** Inflating severity creates noise; the team that owns the domain can always escalate.

## Disposition decision rules (when in doubt)

- **Cannot reproduce given the description** → `needs-info`, with `reasoning` explicitly stating which repro step is missing or contradictory. Do not invent reproductions.
- **"Please make X configurable" / "Add Y feature"** → `feature-request` if framed as a technical capability, `not-a-bug` if framed as a product / UX preference.
- **Symptoms match an open or recently merged PR** → `duplicate` with `duplicate_of` set. Verify with `gh pr view` before asserting.
- **Plugin-only defect, no core code path** → `not-a-bug` with `reasoning` pointing the reporter at the plugin's own repo / vendor.

## Confidence calibration

Confidence is your subjective probability that `disposition + severity + primary domain + duplicate_of` match what a senior Shopware engineer would conclude after the same 5–10 minutes of investigation.

| Range | Meaning |
|---|---|
| `0.90 – 1.00` | Bet money. Verified affected file path + clear repro + no plausible alternatives. |
| `0.70 – 0.89` | Informed estimate. Some ambiguity in domain attribution OR severity. |
| `0.50 – 0.69` | Coin-flip plus signal. Multiple plausible interpretations. |
| `0.30 – 0.49` | Guess with reasoning. Could not find affected code path. |

**Anti-overconfidence rule:** If you are at `≥ 0.85` and your reasoning has no shell-tool evidence (no file paths, no commit SHAs, no related-issue refs), lower confidence by `0.15`.
