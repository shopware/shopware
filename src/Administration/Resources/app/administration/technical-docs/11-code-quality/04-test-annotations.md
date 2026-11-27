# Test annotations

> Status: Convention

A new Jest test in `src/` says in a doc comment why it exists.
The tag also says what may change or delete the test later.
Tagging is a convention that reviewers and agents keep: no check requires a tag.
Once a test is tagged, `sw-test-rules/test-annotation` checks that the tag is used correctly.
The decision is in [`adr/2026-11-05-administration-test-annotations.md`](../../../../../../../adr/2026-11-05-administration-test-annotations.md).

## The three tags

| Tag | The test exists because | `@see` | What may change or delete it |
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

A bare `@regression` or `@behavior` takes the `@see` of an enclosing `describe` with the same tag.
This works for nested `describe` blocks too.
The tag itself is not inherited: a test added to the `describe` later claims `@regression` on its own, where a reviewer can question it.
For the same reason `@characterization` is not allowed on a `describe`.
When you add a test to a tagged `describe`, check that it really covers the bug or decision of that `describe`.

## Accepted sources

| Tag | Accepted `@see` |
|---|---|
| `@regression` | `https://github.com/shopware/<repo>/issues/<n>` or `…/pull/<n>`, with or without an anchor |
| `@behavior` | `https://github.com/shopware/<repo>/discussions/<n>` or `https://github.com/orgs/shopware/discussions/<n>`, optionally with `#discussioncomment-<n>` |
| `@behavior` | An issue or pull request comment: `#issuecomment-<n>`, `#discussion_r<n>`, `#pullrequestreview-<n>` or `#r<n>` |
| `@behavior` | A path to an existing ADR, such as `adr/2026-10-16-administration-lint-gate.md` |

An issue without a comment anchor is not a `@behavior` source: opening an issue is not a decision.
Link the comment where the decision was made.

The allowlist lives in `eslint-rules/test-rules/test-annotation/sources.js`.

### Sources outside the allowlist

When the decision really lives elsewhere, link it with `@see` and add `@seeException` with a reason of at least 20 characters:

```js
/**
 * Toasts stay open on hover, so screen reader users can finish reading them.
 * @behavior
 * @see https://www.w3.org/WAI/WCAG22/Understanding/timing-adjustable
 * @seeException WCAG is the decision, there is no Shopware thread for it.
 */
```

A source that keeps showing up belongs in the allowlist, through its own pull request.
Do not fall back to `@characterization` when the source is merely unusual.

## What the lint rule checks

`sw-test-rules/test-annotation` runs on every spec and reports an error when a tagged test or `describe`:

- has more than one of `@regression`, `@behavior` and `@characterization`
- is a `describe` tagged `@characterization`
- is tagged `@regression` or `@behavior` without a `@see`, on itself or on an enclosing `describe` with the same tag
- links a `@see` outside the accepted sources without a `@seeException`
- has a `@seeException` reason shorter than 20 characters

It does not report untagged tests.

## Existing tests

Tests written before this convention stay untagged.
Tag one when you change it and know why it exists; otherwise leave it.

## Fixing a bug

The test that reproduces the fixed bug is tagged `@regression` and links the bug.
