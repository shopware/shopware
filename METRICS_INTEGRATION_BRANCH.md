# Metrics integration testing branch

> **Branch:** `test-metrics-integration` · **Base:** `trunk` · **Remote:** `origin` (github.com/shopware/shopware)
>
> **Purpose:** integrate all in-flight `feat-metrics-*` branches in one place for end-to-end testing.
> **This branch is throwaway.** It must **never** be merged into `trunk` or opened as a PR against it.
> Do not base real work on it.
>
> **The branch ref only ever moves forward — never force-push it.** A consuming project
> pins it via a `dev-` requirement, and forward-only history lets that project pull updates
> with a plain `composer update` (no cache wipe). See "Consuming this branch" below.

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

## Refreshing this branch (forward-only merges — never force-push)

The branch must only ever move forward, so a consuming project can pull updates with a plain
`composer update` (a force-push/rewrite would break that — see "Consuming this branch"). To
pull in the latest, merge the newest `trunk` and each source branch into the existing branch:

```bash
git fetch origin
git checkout test-metrics-integration

# keep up with trunk first (source branches already track it, but this keeps the base current)
git merge --no-ff origin/trunk -m "merge: trunk"

# merge each source branch in order (use origin/* to get the latest pushed state).
# already-merged, unchanged branches are a no-op; changed ones bring only their new commits.
git merge --no-ff origin/feat-metrics-dal-read        -m "merge: dal-read"
git merge --no-ff origin/feat-metrics-http            -m "merge: http (builds on dal-read)"
git merge --no-ff origin/feat-metrics-cart-checkout   -m "merge: cart-checkout"
git merge --no-ff origin/feat-metrics-message-queue   -m "merge: message-queue"
git merge --no-ff origin/feat-metrics-indexer         -m "merge: indexer"
git merge --no-ff origin/feat-metrics-scheduled-tasks -m "merge: scheduled-tasks"

git push origin test-metrics-integration        # plain push, NO --force
```

### Quick incremental update (one branch changed)

If only one source branch moved:

```bash
git fetch origin
git checkout test-metrics-integration
git merge --no-ff origin/feat-metrics-<changed> -m "merge: <changed>"
git push origin test-metrics-integration
```

### If you must rebuild from scratch

Rewriting history is the deterministic option but **breaks forward-only** and forces every
consumer to reset. Only do it if the merge graph is truly broken, coordinate with consumers
first, and afterwards they must run `composer update shopware/platform` (with a source install
— see below — a plain update still works because git fetch handles the rewrite).

## Consuming this branch from another project

The dependent project resolves `shopware/platform` from `github.com/shopware/shopware`
(same repo/`repositories` entry that already makes `dev-trunk` work). Two changes in its
`composer.json`:

**1. Point the requirement at this branch** (branch `x` → constraint `dev-x`):

```json
"shopware/platform": "dev-test-metrics-integration as 6.7.x-dev"
```

**2. Install this one package from source** so updates never need a cache wipe:

```json
"config": {
    "preferred-install": {
        "shopware/platform": "source"
    }
}
```

Then:

```bash
composer update shopware/platform --with-all-dependencies
```

Why source install: `vendor/shopware/platform` becomes a real git clone, so each update is
an incremental `git fetch` + checkout of the branch HEAD. It touches only this package (the
project's other dependencies stay dist-installed and cached), and it needs **no
`composer clearcache`** — the full cache wipe that would otherwise re-download everything.
Combined with forward-only pushes on this branch, `composer update shopware/platform` is all
a consumer ever runs to get the latest. (One-time cost: the initial platform clone is a bit
slower and leaves a `.git` in `vendor/shopware/platform`.)

Lightweight alternative without changing config: `composer update shopware/platform --no-cache`
bypasses the cache for just that run (only this package is refetched), leaving the global
cache intact.
