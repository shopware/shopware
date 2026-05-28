# Severity, Category, Decision, Confidence

Every finding picks `severity`, `category`, `confidence`. Orchestrator picks `risk_level`, `decision`, top-level `requires_human` for the whole review.

## Severity

Reflects how wrong the change is, not how loud the persona feels.

| Severity   | Use when                                                                                                                    |
| ---------- | --------------------------------------------------------------------------------------------------------------------------- |
| `blocking` | Unsafe to merge: regression, outage, security exposure, data loss, broken public contract. Rare — "do not approve until X". |
| `major`    | Meaningful problem, fix before merge. A senior reviewer would request changes.                                              |
| `minor`    | Real issue, but author push-back / follow-up is reasonable. Better fixed, not wrong if not.                                 |
| `nit`      | Style, taste. Use sparingly — a wall of nits is worse than no nits.                                                         |

Default down when uncertain. Inflating creates noise; the domain owner can always escalate.

## Category

Pick the **dominant** axis (drives the impact statement).

| Category          | Use when                                                                                             |
| ----------------- | ---------------------------------------------------------------------------------------------------- |
| `security`        | Auth, authz, ACL, input validation, secrets, crypto, prompt injection, CSRF/XSS, IDOR, cross-tenant. |
| `correctness`     | Wrong logic — off-by-one, wrong condition, wrong default.                                            |
| `tests`           | Missing/wrong/flaky/empty assertion.                                                                 |
| `maintainability` | Future readers misread/break. Naming, structure, hidden coupling.                                    |
| `performance`     | Algorithm, query patterns, hot-path allocation, bundle size.                                         |
| `compatibility`   | Breaks plugins, older versions, public API; removal without deprecation.                             |
| `docs`            | Missing/misleading docblock, UPGRADE, README, changelog.                                             |
| `supply_chain`    | New dep, version bump, build-tool change.                                                            |
| `privacy`         | PII, GDPR, regional data rules.                                                                      |

## Decision

Computed twice: by each persona-worker (own findings) and by the orchestrator (merged). Same rules; first match wins.

| #   | Decision             | Rule                                                                                                                      |
| --- | -------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| 1   | `block`              | Any `severity: blocking`. Not silently downgraded by `requires_human` (merged shape carries `requires_human` separately). |
| 2   | `needs_human_review` | Any `requires_human: true` (no `blocking`).                                                                               |
| 3   | `request_changes`    | Any `major` with `confidence ≥ 0.8`.                                                                                      |
| 4   | `comment`            | None of the above.                                                                                                        |

Top-level `requires_human` (merged) = `any(findings, f => f.requires_human)`. Orthogonal to `decision`.

| Decision             | `risk_level`                                                       |
| -------------------- | ------------------------------------------------------------------ |
| `block`              | `critical`                                                         |
| `needs_human_review` | `critical` if any `blocking`; `high` if any `major`; else `medium` |
| `request_changes`    | `high`                                                             |
| `comment`            | `medium` if any `major`; else `low`                                |

## Confidence

Number in `[0, 1]`. Subjective probability a senior reviewer would agree.

| Band   | Range       | Meaning                                                                |
| ------ | ----------- | ---------------------------------------------------------------------- |
| high   | `≥ 0.80`    | Bet money. Verified file + context + ≥1 fact about callsite/callers.   |
| medium | `0.50–0.79` | Informed estimate. Some ambiguity, didn't read full call graph.        |
| low    | `< 0.50`    | Worth flagging but not standing behind. Often right for `nit`/`minor`. |

Pick the band, then a representative value (e.g. 0.85, 0.6). Two decimals max.

**Anti-overconfidence cap:** if you'd emit `≥ 0.80` but evidence is _only_ the literal added line (no context read, no callsite, no history), cap at `0.70`.

## Cross-persona dedup (orchestrator)

Group findings by `(file, line, normalised(claim))` — normalised = lower-case, collapsed whitespace, punctuation-stripped.

- Same key, multiple findings → collapse via tie-break, list others in `concurring_personas`.
- Same `(file, line, category)`, different normalised claim → still dedup. Two `docs` findings on `UPGRADE-6.7.md:1` are the same complaint phrased two ways; keep the winning claim, list the rest as concurring. Same `(file, line)` but **different `category`** → keep both (different reasons on one line is signal).
- Different file or line → keep both.

## Confidence floor (orchestrator)

Drop findings whose `confidence` falls below the floor for their severity. Applied **after** dedup, **before** sort/emit. Floors:

| Severity   | Floor    |
| ---------- | -------- |
| `blocking` | any      |
| `major`    | `≥ 0.50` |
| `minor`    | `≥ 0.50` |
| `nit`      | `≥ 0.70` |

### Tie-break (first decisive wins)

1. Highest severity (`blocking` > `major` > `minor` > `nit`).
2. Highest `confidence` (numeric).
3. Category-owning persona wins if present in the group.
4. Persona alphabetical (deterministic fallback).

### Category → owning persona

| Category          | Owner          |
| ----------------- | -------------- |
| `security`        | `security`     |
| `privacy`         | `security`     |
| `supply_chain`    | `security`     |
| `correctness`     | `architecture` |
| `maintainability` | `architecture` |
| `performance`     | `architecture` |
| `compatibility`   | `architecture` |
| `tests`           | `architecture` |
| `docs`            | `open-source`  |

`code-style`, `ux`, `product-owner` don't own a category — they show up as concurring only.

### Mechanics

Winner's `persona` stays. Set `concurring_personas` to other slugs in the group, sorted, deduped. Drop losers. Don't average `confidence` — kept value is the winner's. Sort survivors: severity desc, file asc, line asc.
