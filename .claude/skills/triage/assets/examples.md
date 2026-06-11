# Triage Output — Field Rules & Worked Examples

These are illustrative final outputs in the **strict JSON shape** emitted by the gh aw CI workflow (`.github/workflows/triage.md`). The interactive skill emits the equivalent information as Markdown — the field semantics are identical, only the wire format differs. Your actual reasoning, paths, SHAs, and issue numbers must come from your real investigation — **never invented**.

The interactive Markdown layout is fully specified by the template in SKILL.md "Output format" → no separate example needed here.

## Field rules

| Field | Required | Constraints |
|---|---|---|
| `disposition` | yes | One enum value — see references/CLASSIFICATION.md |
| `severity` | yes | One enum value — see references/CLASSIFICATION.md |
| `suggested_labels` | yes | 1–2 entries from references/DOMAINS.md |
| `confidence` | yes | Number 0.0–1.0 (see calibration in CLASSIFICATION.md) |
| `reasoning` | yes | 2–5 sentences, max 2000 chars, must reference shell findings |
| `evidence_quotes` | yes | 1–5 verbatim spans, max 500 chars each |
| `duplicate_of` | yes | Plain integer issue number (e.g. `15800` — NOT `"15800"`, NOT `"#15800"`) if `disposition == "duplicate"`, else `null` |
| `missing_template_fields` | yes | Informational — empty array if all template sections present |
| `affected_paths` | yes | File paths you identified via `rg`/`find` (empty array if none found) |
| `related_issues` | yes | Array of plain integers (e.g. `[12345, 12346]` — NOT `["#12345"]`, NOT `["12345"]`). Related but NOT `duplicate_of`. |
| `related_prs` | yes | Array of plain integers — merged PR numbers, same shape rule as `related_issues` |
| `recent_commits_in_area` | yes | Short `git log --oneline` entries, max 200 chars each |
| `change_size_estimate` | yes | One enum: `quick-fix` (<30 LOC single file), `small` (single component), `medium` (cross-component), `large` (architectural), `unknown` |

**Emission rules:** the unattended gh aw workflow emits the JSON object as its final message — no markdown code fence, no preamble, no trailing prose. The post-run processor (`.github/workflows/process-triage-result.yml`) runs the validator (`.github/bin/js/validate-triage-output.ts`) to enforce these constraints + scan for secret leakage before publishing deterministic issue updates.

## A — `valid-bug` with affected code identified

```json
{
  "disposition": "valid-bug",
  "severity": "medium",
  "suggested_labels": ["domain/crm-after-sales"],
  "confidence": 0.92,
  "reasoning": "Export downloads lose their filename extension. rg located src/Core/Content/ImportExport/Service/DownloadService.php; git log surfaced 4cfe2b182ba 'fix: ... (#16632)' which closes #16599 — fix already on trunk. Workaround (rename file) exists, hence medium not high.",
  "evidence_quotes": [
    "[issue] a file is generated that has no file extension",
    "[shell] 4cfe2b182ba fix: export temporary url file download missing filename (#16632)"
  ],
  "duplicate_of": null,
  "missing_template_fields": ["expected_behaviour"],
  "affected_paths": ["src/Core/Content/ImportExport/Service/DownloadService.php"],
  "related_issues": [],
  "related_prs": [16632],
  "recent_commits_in_area": ["4cfe2b182ba fix: export temporary url file download missing filename (#16632)"],
  "change_size_estimate": "small"
}
```

## B — `needs-info` (input fundamentally unclear)

```json
{
  "disposition": "needs-info",
  "severity": "low",
  "suggested_labels": ["domain/framework", "component/core"],
  "confidence": 0.45,
  "reasoning": "Body says only 'shop is broken pls fix'. No version, area, actual/expected, or repro. Cannot describe defect. Domain + component labels are placeholders (rubric requires a component/* pair for framework); severity defaults low. No shell tools run.",
  "evidence_quotes": ["[issue] shop is broken pls fix"],
  "duplicate_of": null,
  "missing_template_fields": ["shopware_version", "affected_area", "actual_behaviour", "expected_behaviour", "reproduction_steps"],
  "affected_paths": [],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "unknown"
}
```

## C — `duplicate` (verified via gh search)

```json
{
  "disposition": "duplicate",
  "severity": "medium",
  "suggested_labels": ["domain/framework", "component/administration"],
  "confidence": 0.88,
  "reasoning": "Same defect as #15800: 'sw-media-upload-v2 cannot be cleared'. gh issue view 15800 shows matching actual_behaviour + repro. #15800 still open, no fix on trunk.",
  "evidence_quotes": [
    "[issue] sw-media-upload-v2 ... can't be cleared anymore",
    "[shell] issue #15800: 'media upload cannot be cleared once set'"
  ],
  "duplicate_of": 15800,
  "missing_template_fields": [],
  "affected_paths": ["src/Administration/Resources/app/administration/src/component/form/sw-media-upload-v2"],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "unknown"
}
```

## D — `not-a-bug` (third-party plugin / misfiled support)

```json
{
  "disposition": "not-a-bug",
  "severity": "low",
  "suggested_labels": ["domain/framework", "component/core"],
  "confidence": 0.82,
  "reasoning": "Reporter: 'plugin XYZ doesn't work after install'. rg confirms plugin XYZ is third-party (not in src/). Behaviour matches the plugin's documented `shopware.yaml` config requirement. Not a core defect.",
  "evidence_quotes": [
    "[issue] plugin XYZ doesn't work after install",
    "[shell] rg --files src/ -g 'XYZ*' returned no matches"
  ],
  "duplicate_of": null,
  "missing_template_fields": [],
  "affected_paths": [],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "unknown"
}
```

## E — `feature-request` (technical capability missing, not a regression)

```json
{
  "disposition": "feature-request",
  "severity": "low",
  "suggested_labels": ["domain/inventory"],
  "confidence": 0.79,
  "reasoning": "Reporter wants product list sortable by margin. rg shows sw-product list view exposes a fixed sortable column set (name/stock/price); margin is not a stored column. New capability, not a regression.",
  "evidence_quotes": [
    "[issue] product list does not let me sort by margin",
    "[shell] sortable columns: name, stock, price"
  ],
  "duplicate_of": null,
  "missing_template_fields": ["actual_behaviour"],
  "affected_paths": [],
  "related_issues": [],
  "related_prs": [],
  "recent_commits_in_area": [],
  "change_size_estimate": "unknown"
}
```

These are templates for shape and tone. The schema, taxonomy, and severity rubric are normative; these examples are illustrative.
