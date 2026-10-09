# Output Shape

Emit JSON only in wrapper-fed or persona-worker mode. No markdown fence or prose.

## Per-persona Review

```json
{
    "schema_version": "1",
    "persona": "security",
    "summary": "1-3 short sentences naming a changed file or symbol.",
    "risk_level": "low | medium | high | critical",
    "decision": "comment | request_changes | block | needs_human_review",
    "findings": []
}
```

Each finding:

```json
{
    "severity": "blocking | major | minor | nit",
    "category": "security | correctness | tests | maintainability | performance | compatibility | docs | supply_chain | privacy",
    "file": "repo-relative/path.php",
    "line": 123,
    "claim": "One sentence, no hedging.",
    "evidence": "Verbatim diff or shell quote, redacted if needed.",
    "impact": "One sentence.",
    "suggested_fix": "Specific minimal fix.",
    "confidence": 0.85,
    "requires_human": false,
    "rule_id": "SCOPE-001"
}
```

`rule_id` is optional. Set it when the finding rests on a rule of a loaded guide
(`guides/<slug>.md`, frontmatter `rules[].id`). The orchestrator carries it into
the published comment as `<!-- sw-review:rule=SCOPE-001 -->`, so later a person
can search reviewed PRs for the rules that produce findings nobody acts on.

## Merged Review

```json
{
    "schema_version": "1",
    "pr": { "number": 16638, "head_sha": "abc123def" },
    "personas_run": ["architecture", "security"],
    "personas_skipped": [{ "persona": "ux", "reason": "no UI files" }],
    "guides_applied": ["platform-scope", "bc-php"],
    "summary": "1-3 short sentences naming a changed file or symbol.",
    "risk_level": "low | medium | high | critical",
    "decision": "comment | request_changes | block | needs_human_review",
    "requires_human": false,
    "persona_summaries": { "security": "No findings." },
    "findings": []
}
```

Merged findings add:

```json
{
    "persona": "security",
    "concurring_personas": ["architecture"]
}
```

All other finding fields match the per-persona finding shape.

## Wrapper-fed Input

```json
{
    "personas": ["security", "architecture"],
    "pr": {
        "number": 16638,
        "head_sha": "abc123def",
        "title": "feat(checkout): ...",
        "body": "...",
        "labels": [],
        "author": "octocat",
        "author_association": "MEMBER",
        "base_ref_name": "trunk",
        "head_ref_name": "feature/x",
        "additions": 120,
        "deletions": 8,
        "changed_files": 5
    },
    "diff": "...",
    "files": ["src/Core/..."],
    "commits": [],
    "change_profile": { "signals": ["php_src"], "guides": [] }
}
```

Persona-worker input adds `"guides": ["guides/platform-scope.md"]` (the guides
the orchestrator selected for this persona; the worker reads them whole after
its persona file) and `"change_profile"` (the classify.sh output, see
`references/CLASSIFY.md`).

Rules:

- `persona` means worker mode; `personas` means orchestrator mode. If both exist, `persona` wins.
- Persona-worker input may include `tier` and `budget`. They are runtime hints, not output fields.
- In wrapper-fed orchestrator mode, omit `persona`; omit `personas` too when the orchestrator should auto-select personas from the changed files.
- `pr.number` may be `null` for local diffs.
- `diff` or `diff_path` is required.
- `commits` is optional and used only by `maintainer`.
- `change_profile` and `guides` are optional; without them the worker reviews with its persona lens only.
- Empty `findings`, `personas_skipped`, and `concurring_personas` arrays are valid.
