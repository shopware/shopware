<!--
Frontmatter-free gh aw policy fragment for nightly failure triage.

This file holds only the **gh-aw-mode specifics** — invocation context and
JSON output contract. The **shared policy** (role, trust boundaries,
clustering workflow, tool budget, anti-reward-hacking) lives in
`.github/aw/shared/sw-nightly-policy.md` and is runtime-imported below, so the
interactive skill (.agents/skills/nightly-triage/SKILL.md) and this fragment
cannot drift on the rubric. (Shared policy must live under `.github/` — gh aw
forbids runtime-imports outside `.github/` for security reasons.)
-->

## Context (gh aw mode)

You operate inside the `shopware/shopware` monorepo with read access to the
codebase and to GitHub via MCP tools. The issue you are triaging is an
auto-filed per-domain nightly tracking issue. Your
output is a single structured `NightlyTriageOutput` JSON object consumed by a
deterministic processor and a post-run schema/secret-scan validator
(`.github/bin/js/validate-sw-nightly-output.ts`). You **cannot** label,
close, assign, or comment — the structured result is the only deliverable.

**Hard turn limit.** This run is force-stopped after **50 turns** (roughly one
tool call each), with **no warning**. If you are stopped before emitting, the
run produces **no output at all**, which is worse than any low-confidence
answer. Follow the "bias toward finishing" guidance in the shared tool budget
and emit well before you would expect to be cut off.

**Shallow checkout — `git log` history is NOT available.** This run checks out
the repository at `fetch-depth: 1`. Do not use `git log` for recent-change
evidence; use the GitHub `search_pull_requests` / `search_issues` /
`get_pull_request` MCP tools instead.

**Reading CI logs.** Use the GitHub Actions MCP tools (`get_workflow_run`,
`list_workflow_jobs`, `get_job_logs`) on the run linked in the issue body.
Fetch logs only for failed jobs, and only once per job.

{{#runtime-import .github/aw/shared/sw-nightly-policy.md}}

## Output contract

Emit a single JSON object matching the `NightlyTriageOutput` shape exactly.
**No prose, no markdown fence, JSON only.** No extra fields beyond those
listed here — the post-run validator enforces the exact shape and will fail on
unknown keys, missing fields, or field-name typos.

```json
{
  "summary": "2-5 sentences: how many tests, how many clusters, the headline root causes. Max 2000 chars.",
  "clusters": [
    {
      "signature": "normalized error signature, max 200 chars",
      "root_cause": "mechanism + concrete file/flag/migration reference, or 'mechanism TBD'. Max 1000 chars.",
      "confidence": "confirmed | plausible | mechanism-tbd",
      "owner_label": "domain/... or service/... from DOMAINS.md, or null when unroutable",
      "known_cluster": false,
      "flaky_or_environmental": false,
      "tests": ["Fully\\Qualified\\ClassTest::testMethod"],
      "evidence_quotes": ["[logs] or [issue] or [shell] prefixed verbatim spans, max 500 chars each, max 5 entries"],
      "related_issues": [],
      "related_prs": []
    }
  ]
}
```

Field rules:
- **Both top-level fields are required**; `clusters` holds 1–10 entries
  (0 only when the issue lists no failing tests at all).
- Every failing test from the issue appears in exactly one cluster's `tests`
  (max 30 per cluster; when a cluster has more, keep the first 30 and say so
  in `root_cause`).
- `confidence: confirmed` requires a concrete mechanism in `root_cause`
  (file path, feature flag, migration); otherwise use `plausible` or
  `mechanism-tbd`.
- `owner_label`: one entry from
  `.agents/skills/sw-triage/references/DOMAINS.md`, or `null`. For a
  confirmed cause this is the cause owner (routing overrides applied); for an
  unconfirmed one it is the test-file owner from the issue.
- `known_cluster: true` when the cluster matches the catalogue in
  `.agents/skills/nightly-triage/references/ROUTING.md`; name the catalogue
  entry in `root_cause`.
- `flaky_or_environmental: true` for patterns on ROUTING.md's flaky list
  (OpenSearch-version assertions, transaction-not-closed collateral,
  order-pollution); these keep `confidence: mechanism-tbd` unless proven.
- `evidence_quotes`: prefix each entry `[issue]`, `[logs]`, or `[shell]`.
- `related_issues` / `related_prs`: arrays of plain integers
  (e.g. `17972`, not `"#17972"`).
- Do **NOT** add fields like `issue_number`, `reroutes`, or `title` — they
  are not in the schema and will fail validation.
