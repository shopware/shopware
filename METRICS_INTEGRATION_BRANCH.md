# Metrics integration testing branch

> **Branch:** `test-metrics-integration` · **Base:** `trunk` · **Remote:** `origin` (github.com/shopware/shopware)
>
> **Purpose:** integrate all in-flight `feat-metrics-*` branches in one place for end-to-end testing.
> **This branch is throwaway.** It must **never** be merged into `trunk` or opened as a PR against it.
> It is force-pushed / rebuilt on demand — do not base real work on it.

## Source branches

Merged in the order below. Order matters only where a branch builds on another.

| # | Branch | Based on | Note |
|---|--------|----------|------|
| 1 | `feat-metrics-dal-read`        | trunk    | DAL read metrics. Foundation for `http`. |
| 2 | `feat-metrics-http`            | dal-read | HTTP request metrics. **Contains `dal-read`** — merge it after dal-read. |
| 3 | `feat-metrics-cart-checkout`   | trunk    | Cart calculation + order-placed metrics. |
| 4 | `feat-metrics-message-queue`   | trunk    | Message-queue metrics. |
| 5 | `feat-metrics-indexer`         | trunk    | Entity/DAL indexer metrics. |
| 6 | `feat-metrics-scheduled-tasks` | trunk    | Scheduled-task metrics. |

Branches 3–6 are independent of each other and of dal-read/http; their relative order is
not significant, but keep this order for reproducibility.

All source branches already have current `trunk` merged into them, so re-merging `trunk`
here is not required and no `trunk`-vs-branch conflicts (e.g. the DI XML→PHP migration)
should reappear. Any conflict encountered here is a genuine **cross-feature** conflict
(usually two branches adding entries to the same telemetry config) — resolve by keeping
**both** sides' additions.

## Refreshing this branch (canonical: rebuild from scratch)

Because history here is disposable, the robust way to pull in the latest from every source
branch is to rebuild the branch on top of the newest `trunk`:

```bash
git fetch origin
git checkout test-metrics-integration
git reset --hard origin/trunk           # start clean from latest trunk

# re-add this instructions file (it lives only on this branch)
git checkout origin/test-metrics-integration -- METRICS_INTEGRATION_BRANCH.md
git add METRICS_INTEGRATION_BRANCH.md
git commit -m "docs: metrics integration branch instructions"

# merge each source branch in order (use origin/* to get the latest pushed state)
git merge --no-ff origin/feat-metrics-dal-read        -m "merge: dal-read"
git merge --no-ff origin/feat-metrics-http            -m "merge: http (builds on dal-read)"
git merge --no-ff origin/feat-metrics-cart-checkout   -m "merge: cart-checkout"
git merge --no-ff origin/feat-metrics-message-queue   -m "merge: message-queue"
git merge --no-ff origin/feat-metrics-indexer         -m "merge: indexer"
git merge --no-ff origin/feat-metrics-scheduled-tasks -m "merge: scheduled-tasks"

git push --force-with-lease origin test-metrics-integration
```

### Quick incremental update (one branch changed)

If only one source branch moved and you don't want a full rebuild:

```bash
git fetch origin
git checkout test-metrics-integration
git merge --no-ff origin/feat-metrics-<changed> -m "merge: <changed>"
git push origin test-metrics-integration
```

Prefer the full rebuild when several branches changed or when the merge graph gets messy —
it is deterministic and avoids drift.

## Consuming this branch from another project

See the PR/handover notes. In short, in the dependent project's `composer.json`:

```json
"shopware/platform": "dev-test-metrics-integration as 6.7.x-dev"
```

then `composer update shopware/platform`.
