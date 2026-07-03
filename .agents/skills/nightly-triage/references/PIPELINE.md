# Extraction & Issue-Generation Pipeline

Mechanical steps with the exact commands that worked on the 2026-07-03
`integration-major` sweep. Run everything from the repo root; write
intermediates to the session scratchpad, not the repo.

## 1. Run inventory

```bash
gh run view <RUN_ID> --repo shopware/shopware \
  --json displayTitle,workflowName,headBranch,conclusion,event
gh run view <RUN_ID> --repo shopware/shopware --json jobs \
  --jq '.jobs[] | select(.conclusion=="failure") | "\(.databaseId)\t\(.name)"'
```

Skip aggregator jobs (`*-check`). Give each shard a short slug — it becomes
the per-test provenance tag.

## 2. Per-shard log download + test extraction

```bash
# parallel download (one gh call per failing job)
gh run view --repo shopware/shopware --job <JOB_ID> --log-failed > job-<slug>.log

# PHPUnit failure headers — errors and failures are SEPARATE numbered lists,
# so "1)" appears twice per log; dedup with sort -u
grep -oE '[0-9]+\) Shopware\\Tests\\[A-Za-z0-9\\]+::[A-Za-z_0-9]+' job-<slug>.log \
  | sed 's/^[0-9]*) //' | sort -u > fails-<slug>.txt

# sanity: compare against the PHPUnit summary line
grep -oE 'Tests: [0-9]+, Assertions: [0-9]+.*' job-<slug>.log | tail -1
```

Unique-test counts differ from the summary's Errors+Failures when data-set
variants collapse — expected.

## 3. Per-test error capture (for clustering)

Each `N) Class::method` header is followed by the exception/assertion text.
Strip the `job \t step \t timestamp ` prefix
(`re.sub(r"^.*?\d{7}Z ", "", line)`), take the first non-trace line
(skip lines starting `/`, `#`, `with data set`), truncate ~160 chars.
Normalize before grouping: hex IDs → `<hex>`, long numbers → `<n>`.

**Known truncation:** `WriteException: There are N error(s) while writing
data.` hides the nested per-field errors in `--log-failed`. Do NOT guess the
cause from the count — reproduce locally (REPRODUCTION.md).

## 4. Domain resolution

Use Python, not shell — FQCNs contain backslashes that break shell loops.

1. FQCN → path: `Shopware\Tests\Integration\…` → `tests/integration/…` + `.php`.
2. `#[Package('…')]` on the test file wins.
3. Else mirrored src file: `tests/integration/X` → `src/X`, strip trailing `Test`.
4. Else dominant marker of the mirrored src directory (count with
   `rg -o --no-filename "#\[Package\('([^']+)'\)\]" <dir> -r '$1' | sort | uniq -c`).
5. Else path heuristic from the triage skill's DOMAINS.md.
6. Apply ROUTING.md overrides (they beat all of the above).

## 5. Clustering & routing

Priority order when assigning a test to an issue (first match wins):

1. Confirmed cross-domain root-cause cluster (ROUTING.md catalogue) — routes
   to the root cause's owner.
2. Domain-owned signature cluster (same exception type + normalized message).
3. Residual `ASSERTION` bucket per domain.

A cluster only becomes "confirmed cross-domain" after its mechanism is proven
(trace shows it, or local repro). Until then the member tests stay with their
test-file owner.

## 6. Issue generation

One issue per **domain × job area**, plus one parent. Draft to scratchpad,
get user approval, then create.

Child issue layout (title:
`[nightly][<job-area>] <domain-slug>: <N> failing tests across <S> shard(s) (<date>)`):

- `Tracking issue: #<parent>` (prepend after parent exists)
- **Context** — run link, per-shard job links, count, grouping rule
  ("root-cause owner; confirmed collateral included here")
- **Failure clusters** — per cluster: `#### <title> — <n> test(s)`, root-cause
  paragraph (with `file:line` and repro status: reproduced / trace-confirmed /
  mechanism TBD) or sample error in a code fence, then
  `<details><summary>Affected tests</summary>` list of
  `` `ClassTest::method` (shard) ``.

Labels: the domain label; `domain/framework` additionally gets exactly one
`component/*`. Team = label only — no assignees, no @-mentions.

Parent issue: context (why the run is red — e.g. suite reinstated after a
gap, so breakage accumulated), a table `Issue | Domain | Tests | Headline
root cause`, the attribution method, a note that counts follow root-cause
ownership, and a definition of done (the workflow's aggregate check green).

Reconcile totals: sum of children == extracted unique test entries. Always.

## 7. Post-filing

- `gh issue edit` each child to prepend the tracking line.
- If routing changes later (audit finds hidden collateral), regenerate the
  affected bodies from the data files — don't hand-edit lists — and update
  the parent table in the same pass.
