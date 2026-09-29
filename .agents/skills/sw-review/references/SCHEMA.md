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
    "requires_human": false
}
```

## Merged Review

```json
{
    "schema_version": "1",
    "pr": { "number": 16638, "head_sha": "abc123def" },
    "personas_run": ["architecture", "security"],
    "personas_skipped": [{ "persona": "ux", "reason": "no UI files" }],
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
    "commits": []
}
```

Rules:

- `persona` means worker mode; `personas` means orchestrator mode. If both exist, `persona` wins.
- Persona-worker input may include `tier` and `budget`. They are runtime hints, not output fields.
- In wrapper-fed orchestrator mode, omit `persona`; omit `personas` too when the orchestrator should auto-select personas from the changed files.
- `pr.number` may be `null` for local diffs.
- `diff` or `diff_path` is required.
- `commits` is optional and used only by `open-source`.
- Empty `findings`, `personas_skipped`, and `concurring_personas` arrays are valid.
