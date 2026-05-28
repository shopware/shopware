# Output Schema

Two shapes, depending on role:

- **Per-persona** — emitted by a persona-worker subagent.
- **Merged** — emitted by the orchestrator after fan-out + dedup.

Strict JSON Schema covers both via `$defs` in `assets/review-output.schema.json` (normative; if prose disagrees, schema wins).

---

## Per-persona shape

```json
{
    "schema_version": "1",
    "persona": "security",
    "summary": "PR description + main risk.",
    "risk_level": "low | medium | high | critical",
    "decision": "comment | request_changes | block | needs_human_review",
    "findings": [
        {
            "severity": "blocking | major | minor | nit",
            "category": "security | correctness | tests | maintainability | performance | compatibility | docs | supply_chain | privacy",
            "file": "src/Core/...",
            "line": 123,
            "claim": "What is wrong.",
            "evidence": "Verbatim diff/code quote.",
            "impact": "What can happen if this ships.",
            "suggested_fix": "Specific, minimal remediation.",
            "confidence": 0.85,
            "requires_human": false
        }
    ]
}
```

| Field                       | Constraint                                                                                                                                                                                                                                                                                                                        |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `schema_version`            | Literal `"1"`.                                                                                                                                                                                                                                                                                                                    |
| `persona`                   | Slug used (`security`, `architecture`, …).                                                                                                                                                                                                                                                                                        |
| `summary`                   | 1–3 sentences. Name at least one concrete file/symbol from shell output. Truncate to 1000 chars (append `…`).                                                                                                                                                                                                                     |
| `risk_level`                | Derived from this persona's highest-severity finding (see `CLASSIFICATION.md`).                                                                                                                                                                                                                                                   |
| `decision`                  | Computed from this persona's findings only. Orchestrator recomputes across all.                                                                                                                                                                                                                                                   |
| `findings`                  | Array, possibly empty. Empty is valid when persona has nothing to say.                                                                                                                                                                                                                                                            |
| `findings[].severity`       | Enum.                                                                                                                                                                                                                                                                                                                             |
| `findings[].category`       | Dominant axis only.                                                                                                                                                                                                                                                                                                               |
| `findings[].file`           | Repo-relative path observed in the diff.                                                                                                                                                                                                                                                                                          |
| `findings[].line`           | 1-indexed line in post-change file. If no specific line, use first line of the hunk.                                                                                                                                                                                                                                              |
| `findings[].claim`          | One sentence, no hedging. Truncate to 280 chars.                                                                                                                                                                                                                                                                                  |
| `findings[].evidence`       | Verbatim quote from diff/shell output. Truncate to 500 chars. **Secret-redaction exception:** if the cited substring contains a secret (long random / base64 / hex / `*_TOKEN=` / `*_KEY=` / `*_SECRET=`), substitute the secret span with `[REDACTED_KEY]`; surrounding line stays verbatim. See `BOUNDARIES.md` §4, `TOOLS.md`. |
| `findings[].impact`         | One sentence. Truncate to 280 chars.                                                                                                                                                                                                                                                                                              |
| `findings[].suggested_fix`  | Minimal, specific. Reference Shopware patterns by name. Truncate to 500 chars.                                                                                                                                                                                                                                                    |
| `findings[].confidence`     | Number `[0, 1]`. Bands + anti-overconfidence cap in `CLASSIFICATION.md` §Confidence.                                                                                                                                                                                                                                              |
| `findings[].requires_human` | `true` only when a human must validate before merge.                                                                                                                                                                                                                                                                              |

---

## Merged orchestrator shape

```json
{
    "schema_version": "1",
    "pr": { "number": 16638, "head_sha": "abc123" },
    "personas_run": [
        "architecture",
        "code-style",
        "open-source",
        "product-owner",
        "security"
    ],
    "personas_skipped": [{ "persona": "ux", "reason": "no UI files changed" }],
    "summary": "Cross-cutting PR purpose + dominant risk.",
    "risk_level": "high",
    "decision": "request_changes",
    "requires_human": false,
    "persona_summaries": { "security": "…", "architecture": "…" },
    "findings": [
        {
            "persona": "security",
            "concurring_personas": ["architecture"],
            "severity": "blocking",
            "category": "security",
            "file": "src/Core/...",
            "line": 27,
            "claim": "…",
            "evidence": "…",
            "impact": "…",
            "suggested_fix": "…",
            "confidence": 0.9,
            "requires_human": true
        }
    ]
}
```

| Field                            | Constraint                                                                                                                                        |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| `pr.number`                      | Positive integer (PR mode) or `null` (local-diff mode).                                                                                           |
| `pr.head_sha`                    | Short or full SHA.                                                                                                                                |
| `personas_run`                   | Persona slugs that produced a review. Emit alphabetised; schema validates only `uniqueItems` + `minItems: 1`. No hard "always-on" — gate decides. |
| `personas_skipped`               | `[{persona, reason}]`. Reason ≤ 280 chars. Empty array allowed.                                                                                   |
| `summary`                        | 1–3 sentences. Truncate to 1500 chars. Names a concrete file/symbol.                                                                              |
| `risk_level`, `decision`         | Computed from merged findings (`CLASSIFICATION.md`).                                                                                              |
| `requires_human`                 | `true` iff any finding has `requires_human: true`. Orthogonal to `decision` — a `block` with `requires_human: true` is still `block`.             |
| `persona_summaries`              | Map slug → that worker's `summary`. One entry per `personas_run` slug.                                                                            |
| `findings`                       | Deduped, flat. Each carries `persona` and `concurring_personas`. Sorted: severity desc, file asc, line asc.                                       |
| `findings[].persona`             | Slug that owns the kept dedup copy.                                                                                                               |
| `findings[].concurring_personas` | Other slugs that raised the same `(file, line, normalised claim)`. Empty when only one persona raised. Alphabetised.                              |
| `findings[].*` (rest)            | Same as per-persona shape, including secret-redaction.                                                                                            |

Orchestrator never invents content — kept findings come verbatim from a worker; it only dedupes and tags concurring.

---

## Wrapper-fed input shape (`<input_json>` or `<input_json_${NONCE}>`)

```json
{
    "persona": "security",
    "personas": ["security", "architecture"],
    "pr": {
        "number": 16638,
        "head_sha": "abc123def",
        "title": "feat(checkout): …",
        "body": "…",
        "is_draft": false,
        "labels": ["area/checkout"],
        "author": "octocat",
        "author_association": "MEMBER",
        "base_ref_name": "trunk",
        "head_ref_name": "feature/x",
        "additions": 120,
        "deletions": 8,
        "changed_files": 5
    },
    "commits": [
        {
            "sha": "abc123def",
            "message": "fix: correct checkout rounding",
            "verification": { "verified": true, "reason": "valid" }
        }
    ],
    "linked_issues": [
        {
            "number": 12345,
            "title": "Checkout rounds tax incorrectly",
            "body": "…",
            "state": "open",
            "labels": ["domain/checkout"]
        }
    ],
    "diff": "…",
    "diff_path": "/tmp/review-abc123-a8f3.diff",
    "files": ["src/Core/…"]
}
```

| Field                                              | Notes                                                                            |
| -------------------------------------------------- | -------------------------------------------------------------------------------- |
| `persona` / `personas`                             | Exactly one required. String → worker. Array → orchestrator. Both → string wins. |
| `pr.number`                                        | Positive integer (PR mode) or `null` (local-diff).                               |
| `pr.head_sha`                                      | Min 7 chars.                                                                     |
| `pr.title`, `pr.body`                              | Strings, may be empty.                                                           |
| `pr.is_draft`                                      | Boolean, defaults `false`.                                                       |
| `pr.labels`                                        | Array of strings.                                                                |
| `pr.author`, `pr.author_association`               | May be `null` (local-diff). Workers must guard for `null`.                       |
| `pr.base_ref_name`, `pr.head_ref_name`             | Branch names.                                                                    |
| `pr.additions`, `pr.deletions`, `pr.changed_files` | Integers; used by size-cap throttle (`SKILL.md` §Step 2a).                       |
| `commits`                                          | Optional commit metadata for `open-source`. If absent, commit-hygiene checks are unavailable and must be declared in the summary. |
| `linked_issues`                                    | Optional issue bodies for `fixes #N` checks. If absent, workers may check for regression tests but must not claim the root cause was missed. |
| `diff` or `diff_path`                              | Exactly one. `diff_path` when oversize.                                          |
| `files`                                            | Repo-relative paths in the diff.                                                 |

Wrapper applies PII redaction (`BOUNDARIES.md` §4); skill does not undo it.
