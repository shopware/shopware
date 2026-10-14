import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildComment, type FailureDetails, notifyBackportFailure } from './backport-failure.ts';

const details = (overrides: Partial<FailureDetails> = {}): FailureDetails => ({
    targetBranch: '6.7.15.x',
    pullRequest: { owner: 'shopware', repo: 'shopware', number: 21620 },
    runUrl: 'https://github.com/shopware/shopware/actions/runs/1',
    mentions: ['merger', 'assignee'],
    ...overrides,
});

test('buildComment names the target branch, links the run and mentions everyone once', () => {
    const body = buildComment(details({ mentions: ['merger', 'assignee', 'merger', ''] }));

    assert.match(body, /Backport of #21620 to `6\.7\.15\.x` failed/);
    assert.match(body, /https:\/\/github\.com\/shopware\/shopware\/actions\/runs\/1/);
    assert.match(body, /cc @merger @assignee$/);
});

test('buildComment leaves the cc line out when there is nobody to mention', () => {
    assert.doesNotMatch(buildComment(details({ mentions: [] })), /cc/);
});

test('notifyBackportFailure comments on the pull request and on the linked issue', async () => {
    const comments: Array<{ owner: string; repo: string; issue_number: number }> = [];
    const github = {
        rest: {
            issues: {
                async createComment({ owner, repo, issue_number }: { owner: string; repo: string; issue_number: number }) {
                    comments.push({ owner, repo, issue_number });
                },
            },
        },
    };

    await notifyBackportFailure(github, details({ linkedIssue: { owner: 'shopware', repo: 'shopware', number: 100 } }));
    assert.deepEqual(comments, [
        { owner: 'shopware', repo: 'shopware', issue_number: 21620 },
        { owner: 'shopware', repo: 'shopware', issue_number: 100 },
    ]);

    comments.length = 0;
    await notifyBackportFailure(github, details());
    assert.deepEqual(comments, [{ owner: 'shopware', repo: 'shopware', issue_number: 21620 }]);
});
