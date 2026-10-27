# Review guides

One file per topic.
Each guide holds at most 10 rules, a "Do not flag" list for what CI already checks, severity anchors and a "Retired" list.
`index.json` decides which guides a pull request gets; `scripts/classify.ts` applies it.

These files have no code owner.
Any team changes them with a normal pull request, reviewed like any other change.

## Change a rule

1. Edit the bullet in the guide.
   Keep the shape: what to look for, why it matters, the fix, the `Rule:` link, one real example.
2. If the rule's wording moved to a guideline, point `source` in the frontmatter at that heading.
3. Open the pull request; no test run is needed for a wording change.

## Retire a rule

Move the bullet to the "Retired" section with one line on why, keep its id.
Ids are never reused, because published review comments carry them.

## Add a rule

1. Add the bullet with the next free id of the guide and a `since` date in the frontmatter.
2. Prefer a fixture: a real diff under `tests/guide/<guide>/<case>/` with `input.json` and a `grader.ts` that requires the finding, plus a task in `tests/eval.yaml`.
   The fixture is the proof that the rule is specific enough for the model to apply.

## Add a guide

1. Create `guides/<slug>.md` with the same sections as the others.
2. Add an entry to `index.json`: personas, and the signals, path globs or diff anchors that select it.
3. Add a routing case under `tests/routing/<case>/` whose `expected.json` lists the new guide, and run
   `node --test scripts/*.test.ts tests/*.test.ts tests/routing/*.test.ts` from the skill root.
4. Add at least one catch fixture under `tests/guide/<slug>/`.

Guides are picked from the changed code only, never from the pull request text.
`references/CLASSIFY.md` describes the signals and the output.
