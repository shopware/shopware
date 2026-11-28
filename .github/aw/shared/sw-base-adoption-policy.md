# Base Adoption Policy (shared)

Rubric of the base adoption audit.
Loaded by the interactive skill (`.agents/skills/sw-base-adoption/SKILL.md`).

## Audited files

Audit only the files the branch owns: the `owned_files` list of `base-adoption-input.json`.
A file is owned when its content differs from the base the branch last merged, or when that base does not have it.
A base file that arrived through a merge is never a finding.

## Criterion

A finding exists when the base moved a pattern from one form to another and an owned file still has the earlier form.
Never drop a candidate because no observable defect follows from it.

- The move happened inside the audited range.
  A rule that held with the same meaning at the range start, or a commit that only moves, reformats or rewords a rule, is no move.
- A pattern is a rule for authors, an enforced lint or analysis rule, or a migration that the base applied to its own code or declared as the required form.
  A tool that the base only ships is no pattern.
- Branch code counts as new code for a base rule about new code, also when the branch code is older than the rule.
- A decision that the branch documents for itself does not exempt it from a base rule.
  Report the finding and quote the decision in the note.
- An exemption entry (baseline, suppression, ignore or disable comment) that keeps a base rule away from an owned file is the earlier form.

## Verdicts

- `finding`: an owned file has the earlier form.
- `already_conforms`: the owned files in the domain have the new form.
- `not_applicable`: name the reason, either the domain the branch does not touch or an exception that the base rule names itself.
- `cannot_determine`: name what is missing.

## Finding classes

- `required-for-green`: a gate, a generator check or a test enforces the new form after the merge.
  It belongs into the merge.
- `adoption`: nothing enforces the new form, or an exemption entry keeps the enforcing gate green.
  It becomes its own change or issue, never part of the merge commit.

## Evidence

Every finding carries:

- the base commit;
- the base's before and after form, quoted;
- the branch location as `path:line`, read from the file;
- the number of base files that still have the earlier form at the range end.

A search with zero results states its scope and one positive control.

## Out of scope

- Reviewing the base's incoming commits for defects.
- Judging whether an optional pattern is worth adopting.
  Report that the base introduced it and where the branch differs.

## Report format

1. First line: head, base, range, commit count, mode.
2. `required-for-green` findings.
3. `adoption` findings, at most 15 in the body, the rest counted.
4. One line per item with another verdict.

Word findings neutrally: "the base introduced X in `<sha>`; `path:line` has the earlier form".
No confidence scores, no reasoning narrative.
