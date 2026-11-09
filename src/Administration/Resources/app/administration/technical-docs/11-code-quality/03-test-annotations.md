# Test annotations

> Status: Stable

Every new Jest test in `src/` says in a doc comment why it exists.
The tag also says what may change or delete the test later.
The decision is in [`adr/2026-11-05-administration-test-annotations.md`](../../../../../../../adr/2026-11-05-administration-test-annotations.md).

## The three tags

| Tag | The test exists because | Required `@see` | What may change or delete it |
|---|---|---|---|
| `@regression` | A real bug happened, and it must not come back | The bug: a GitHub issue, or the pull request when there is no issue | Only removing the code path the bug was in |
| `@behavior` | Someone decided this behavior | The place of the decision: a GitHub discussion, a comment link, or an ADR | Only revisiting that decision |
| `@characterization` | It pins what the code does today, nobody signed it off | None | Any intended change; update or delete it without discussion |

Pick the tag by the evidence you can link, not by how important the test feels:

1. The test reproduces a bug that happened: `@regression`.
2. You can link the place where this exact behavior was decided: `@behavior`.
3. Anything else: `@characterization`.

`@characterization` is the honest default, not a lower grade.
It tells the next reader that the asserted output is not a decision and may change.
Tests an agent derives from the current code are characterization tests, unless a linked decision states what they assert.

## Writing the doc comment

The description says what the test name can't: the bug as far as this test covers it, or the decision behind the behavior.
Leave it out when the name already says everything.

```js
/**
 * Selected rows were dropped when the listing changed page,
 * because the selection was keyed by row index instead of entity id.
 * @regression
 * @see https://github.com/shopware/shopware/issues/12345
 */
it('should keep selected rows after going to the next page', async () => { … });

/** @characterization */
it('should render the selection count badge', async () => { … });
```

Keep the tags out of the test name.
Test names stay plain sentences about the behavior, and reporters print them on every run.

## Several tests for one bug or decision

Every test carries its own tag.
The `@see` and the description may sit on the enclosing `describe` instead, so they are written once:

```js
/**
 * Selected rows were dropped when the listing changed page.
 * @regression
 * @see https://github.com/shopware/shopware/issues/12345
 */
describe('selection across pagination', () => {
    /** @regression */
    it('should keep selected rows after going to the next page', async () => { … });

    /** @regression */
    it('should keep selected rows after changing the page size', async () => { … });

    /** @characterization */
    it('should render the selection count badge', async () => { … });
});
```

A bare `@regression` or `@behavior` takes the `@see` of the nearest `describe` that has one.
That `describe` must carry the same tag, otherwise the test needs its own `@see`.
The tag itself is never inherited: a test added to the `describe` later has to claim `@regression` on its own, where a reviewer can question it.
For the same reason `@characterization` is not allowed on a `describe`.

When you add a test to a tagged `describe`, check that it really covers the bug or decision of that `describe`.
ESLint can't check that.

## Accepted sources

| Tag | Accepted `@see` |
|---|---|
| `@regression` | `https://github.com/shopware/<repo>/issues/<n>` or `…/pull/<n>`, with or without an anchor |
| `@behavior` | `https://github.com/shopware/<repo>/discussions/<n>`, optionally with `#discussioncomment-<n>` |
| `@behavior` | An issue or pull request comment: `#issuecomment-<n>`, `#discussion_r<n>` or `#pullrequestreview-<n>` |
| `@behavior` | A path to an existing ADR, such as `adr/2026-10-16-administration-lint-gate.md` |

An issue without a comment anchor is not a `@behavior` source: opening an issue is not a decision.
Link the comment where the decision was made.

The allowlist lives in `eslint-rules/test-rules/test-annotation/sources.js`.

### Sources outside the allowlist

When the decision really lives elsewhere, add `@seeException` with a reason of at least 20 characters:

```js
/**
 * Toasts stay open on hover, so screen reader users can finish reading them.
 * @behavior
 * @see https://www.w3.org/WAI/WCAG22/Understanding/timing-adjustable
 * @seeException WCAG is the decision, there is no Shopware thread for it.
 */
```

`sw-test-rules/test-annotation-see-exception` reports every exception as a warning in each lint run, so they stay visible.
A source that keeps showing up belongs in the allowlist, through its own pull request.
Do not fall back to `@characterization` when the source is merely unusual.

## Existing tests

Tests that were already untagged where your branch starts may stay untagged.
The rule asks git for that commit: the merge base of `HEAD` and `origin/trunk`.
It compares tests by identity, the titles of the enclosing `describe` blocks and of the test, as written in the source, joined with ` > `.

| You … | Then |
|---|---|
| Add a test | Tag it. |
| Rename a test or one of its `describe` blocks | It is a new test. Tag it. |
| Move a spec, or move tests to another spec, keeping their names | Nothing to do. |
| Tag or delete an untagged test | Nothing to do. |

`TEST_ANNOTATION_BASE` sets another target instead of `origin/trunk`, for example the branch a stacked pull request builds on.
In CI it is the base commit of the pull request.
The rule reads all specs at the base once per base commit and caches them in `node_modules/.cache/test-annotation`.

When git can't tell which tests are new, the rule reports that once at the top of the first spec it lints, with the cause and the fix, and lets untagged tests through:

- **No merge base**: fetch the target branch with `git fetch origin trunk`, or set `TEST_ANNOTATION_BASE`.
- **No repository**: a worktree links to its repository by an absolute path, which doesn't exist where lint runs, for example in a container that mounts the checkout elsewhere.
  Run `git worktree repair --relative-paths` inside the worktree on the host (git 2.48 or later).

`sw-test-rules/test-annotation` can't be disabled with an `eslint-disable` comment, and the growth check rejects it in `eslint-suppressions.json`.

## Bug fixes need a regression test

The workflow `.github/workflows/regression-test-gate.yml` checks every pull request that closes a bug.
A bug is an issue of type Bug or with the `bug` label, linked as closing in the pull request.
When the pull request also changes Administration source code, it must add at least one doc comment tagged `@regression` whose `@see` links one of the closed bugs.

When the bug can't be covered by Jest, a maintainer adds the `no-regression-test` label.
The job then passes and names the skipped bug in its summary.

## Rules

| Rule | Severity | Checks |
|---|---|---|
| `sw-test-rules/test-annotation` | error | Exactly one tag per test, a valid `@see` for `@regression` and `@behavior`, a reason for `@seeException` |
| `sw-test-rules/test-annotation-see-exception` | warning | Lists every `@seeException` |

Both apply to `src/**/*.spec.{js,ts}` and the specs in `.spec/` directories.
