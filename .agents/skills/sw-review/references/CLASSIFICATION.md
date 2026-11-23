# Classification

## Severity

- `blocking`: unsafe to merge; regression, security exposure, data loss, or broken public contract.
- `major`: meaningful issue a senior reviewer would request changes for.
- `minor`: real issue, but follow-up or author push-back is reasonable.
- `nit`: taste/style only; rare.

Default down when uncertain.

## Category

- `security`: auth, ACL, validation, secrets, crypto, CSRF/XSS, IDOR, tenant boundaries.
- `correctness`: wrong logic, condition, default, or behavior.
- `tests`: missing, wrong, flaky, or empty assertion.
- `maintainability`: confusing structure, naming, or coupling.
- `performance`: hot-path query/allocation/algorithm cost.
- `compatibility`: public API or plugin breakage.
- `docs`: UPGRADE, changelog, README, docblock.
- `supply_chain`: dependency or build-tool risk.
- `privacy`: PII, GDPR, regional data rules.
- `scope`: the change does not belong in the platform (workaround for a misconfigured setup, option for one hosting variant, symptom fix at the wrong layer).

## Decision And Risk

First matching rule wins:

| Condition                               | Decision             | Risk                              |
| --------------------------------------- | -------------------- | --------------------------------- |
| any `blocking` with confidence `< 0.80` | `needs_human_review` | `critical`                        |
| any `blocking`                          | `block`              | `critical`                        |
| any `requires_human`                    | `needs_human_review` | `high` (`medium` if no major+)    |
| any `major` with confidence `>= 0.70`   | `request_changes`    | `high`                            |
| otherwise                               | `comment`            | `medium` if any major, else `low` |

Top-level `requires_human` is true when any kept finding has it.

## Confidence

- `>= 0.80`: verified with context beyond the changed line.
- `0.70-0.79`: actionable but some ambiguity remains.
- `0.55-0.69`: only enough for `minor`.
- `< 0.55`: weak; do not emit unless `blocking`.

If evidence is only the literal changed line, cap confidence at `0.70`.

## Dedupe

Group by `(file, line, normalized claim)`.
Also collapse same `(file, line, category)` when wording differs but the issue is the same.
Collapse same `(file, rule_id)` across personas: two workers loading the same guide report the same rule once.
When different guides describe the same change at the same `(file, line)` with different rule ids (for example a scope rule, a queue rule and a cache rule on one moved cleanup call), keep one finding: the highest severity, then the guide listed first in `guides_applied`; put the other ids into `related_rule_ids`.
Keep distinct categories otherwise.

Tie-break:

1. Highest severity.
2. Highest confidence.
3. Category owner.
4. Persona alphabetical.

Category owners:

| Category                                                                  | Owner          |
| ------------------------------------------------------------------------- | -------------- |
| `security`, `privacy`, `supply_chain`                                     | `security`     |
| `correctness`, `tests`, `maintainability`, `performance`, `compatibility` | `architecture` |
| `docs`, `scope`                                                           | `maintainer`   |

`code-style` and `ux` can concur, but do not own a category.

Apply confidence floors after dedupe:

- `blocking`: no floor; if `< 0.80`, set `requires_human: true`.
- `major`: `>= 0.70`.
- `minor`: `>= 0.55`.
- `nit`: `>= 0.80`.

Drop below-floor findings silently before computing risk or rendering output.
Risk levels (`low`, `medium`, `high`, `critical`) are review-level only; never use them as finding severities.
