# Verification

Before emitting a finding, run this deterministic check. If any required check fails, drop the finding or lower confidence and declare the gap in `summary`.

## Finding checks

- `file` appears in the changed file list.
- `line` is on the post-change side of the diff, or is the first post-change line of the hunk when the issue spans a block.
- `evidence` is a verbatim quote from the diff or shell output observed in this run.
- Secret or PII spans in `evidence` are surgically redacted with the placeholders from `TOOLS.md`.
- The claim is about changed behaviour or a requirement triggered by the diff. Absence-only complaints need an explicit trigger.
- The persona owns the concern. If not, drop it and let the owning persona raise it.
- `confidence >= 0.80` only when context beyond the literal changed line was inspected.

## Wrapper-fed gaps

Wrapper-fed workers must not call `gh`. If optional inputs are missing:

- No `commits` → skip commit-hygiene findings and mention that commit checks were unavailable.
- No `linked_issues` → do not claim the root cause of `fixes #N` was missed. You may still flag missing regression tests when the PR body says it fixes an issue.
- No full `diff` and `diff_path` cannot be read → emit summary-only output with `decision: needs_human_review`.

## Bias guard

Classify each candidate before writing it:

- True positive: backed by diff, context, and a concrete triggered rule.
- False-positive risk: moved/deleted code, generated files, legacy UI migration pressure, missing docs without a trigger, body wording drift, or issue data unavailable.
- Human-needed: domain correctness depends on pricing, tax, migrations, public API timing, license, compliance, or unclear blast radius.

Drop false-positive-risk candidates unless the trigger and evidence are both strong.
