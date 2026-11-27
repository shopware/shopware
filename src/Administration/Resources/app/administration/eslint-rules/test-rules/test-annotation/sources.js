/**
 * @sw-package framework
 */

const { existsSync } = require('node:fs');
const { join } = require('node:path');

const REPOSITORY = String.raw`https://github\.com/shopware/[\w.-]+`;

const ISSUE_OR_PULL_REQUEST = new RegExp(String.raw`^${REPOSITORY}/(issues|pull)/\d+(#[\w-]+)?$`);
const DISCUSSION = new RegExp(String.raw`^${REPOSITORY}/discussions/\d+(#discussioncomment-\d+)?$`);
// A comment, not the issue or pull request itself: opening a thread is not a decision.
const COMMENT = new RegExp(
    String.raw`^${REPOSITORY}/(issues|pull)/\d+(/files)?#(issuecomment-\d+|discussion_r\d+|pullrequestreview-\d+)$`,
);
const ADR = /^adr\/[\w.-]+\.md$/;

/**
 * Whether a `@see` target is evidence for a test of the given kind.
 * A regression points at the bug it reproduces, a behavior at the place where the behavior was decided.
 *
 * @example
 * isAcceptedSource('regression', 'https://github.com/shopware/shopware/issues/123', repoRoot); // true
 * isAcceptedSource('behavior', 'https://github.com/shopware/shopware/issues/123', repoRoot); // false, needs a comment anchor
 * isAcceptedSource('behavior', 'adr/2026-10-16-administration-lint-gate.md', repoRoot); // true when the file exists
 */
function isAcceptedSource(kind, source, repoRoot) {
    if (kind === 'regression') {
        return ISSUE_OR_PULL_REQUEST.test(source);
    }

    if (ADR.test(source)) {
        return Boolean(repoRoot) && existsSync(join(repoRoot, source));
    }

    return DISCUSSION.test(source) || COMMENT.test(source);
}

const EXPECTED_SOURCES = {
    regression: 'a GitHub issue or pull request under github.com/shopware/',
    behavior:
        'a GitHub discussion, a comment link (#issuecomment-…, #discussion_r…, #pullrequestreview-…) or an existing adr/….md path',
};

module.exports = {
    isAcceptedSource,
    EXPECTED_SOURCES,
};
