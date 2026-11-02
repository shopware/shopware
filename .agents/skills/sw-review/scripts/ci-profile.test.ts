import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ciState, metaFromPullRequest, stepSummary, type CiProfile } from './ci-profile.ts';

const pullRequest = {
    base: { ref: 'trunk', repo: { full_name: 'shopware/shopware' } },
    head: { repo: { full_name: 'contributor/shopware' } },
    author_association: 'CONTRIBUTOR',
    labels: [{ name: 'external-contribution' }, { name: 'qi/sw-review' }],
    body: 'Some text\n\nfixes #123',
};

test('metaFromPullRequest maps the REST pull request to the classifier metadata', () => {
    assert.deepEqual(metaFromPullRequest(pullRequest), {
        fork: true,
        author_association: 'CONTRIBUTOR',
        labels: ['external-contribution', 'qi/sw-review'],
        fixes_issue: true,
    });
});

test('metaFromPullRequest treats a same-repository head as no fork and a deleted fork as a fork', () => {
    const sameRepo = { ...pullRequest, head: { repo: { full_name: 'shopware/shopware' } } };
    assert.equal(metaFromPullRequest(sameRepo).fork, false);

    const deletedFork = { ...pullRequest, head: { repo: null } };
    assert.equal(metaFromPullRequest(deletedFork).fork, true);
});

test('metaFromPullRequest detects every issue-link spelling and nothing else', () => {
    const linked = ['Fixes: #12', 'fixed #1', 'closes #3', 'Closed #4', 'resolve #5', 'resolves  #6'];
    for (const body of linked) {
        assert.equal(metaFromPullRequest({ ...pullRequest, body }).fixes_issue, true, body);
    }

    const unlinked = ['fixture #12', 'relates #20514', 'fix without a number', 'fixes#12', null];
    for (const body of unlinked) {
        assert.equal(metaFromPullRequest({ ...pullRequest, body }).fixes_issue, false, String(body));
    }
});

test('ciState counts checks and names the failed ones', () => {
    assert.deepEqual(ciState([]), { checks: 0, failed: [], pending: 0 });
    assert.deepEqual(ciState([
        { name: 'PHPUnit for unit', bucket: 'fail' },
        { name: 'Jest (Admin)', bucket: 'pass' },
        { name: 'Acceptance', bucket: 'pending' },
        { name: 'Lint', bucket: 'skipping' },
    ]), { checks: 4, failed: ['PHPUnit for unit'], pending: 1 });
});

test('stepSummary names the trusted commit, the merge base, missing guides and failed checks', () => {
    const profile: CiProfile = {
        base_ref: 'trunk',
        rules_source: 'abc123',
        path_classes: ['core'],
        signals: ['php_src', 'public_surface'],
        metadata: { fork: false, author_association: 'MEMBER', labels: [] },
        size: { files: 201, changed_lines: 12, over_cap: true },
        path_groups: [],
        merged_from_base: [{ sha: '1', subject: 'merge' }],
        guides: [
            { guide: 'bc-php', personas: ['architecture'], matched_by: ['always:php_src'], file_exists: true },
            { guide: 'new-guide', personas: ['maintainer'], matched_by: ['signal:config'], file_exists: false },
        ],
        all_guides: [],
        ci: { checks: 3, failed: ['PHPUnit for unit'], pending: 0 },
    };

    const summary = stepSummary(profile, 'trunk', 'def456', 'abc123');

    assert.match(summary, /^### sw-review change profile\n/);
    assert.match(summary, /scripts and rules: `abc123`; base: `trunk`; merge base: `def456`/);
    assert.match(summary, /signals: php_src, public_surface/);
    assert.match(summary, /guides: bc-php, new-guide \(missing\)/);
    assert.match(summary, /201 files, 12 changed lines \(over cap: split slices, never skip\); base commits merged in: 1; CI failed checks: PHPUnit for unit/);
});

test('stepSummary prints "none" when no check failed and no cap note when under cap', () => {
    const profile: CiProfile = {
        base_ref: 'trunk',
        rules_source: 'abc123',
        path_classes: [],
        signals: [],
        metadata: { fork: false, author_association: '', labels: [] },
        size: { files: 1, changed_lines: 2, over_cap: false },
        path_groups: [],
        merged_from_base: [],
        guides: [],
        all_guides: [],
        ci: { checks: 0, failed: [], pending: 0 },
    };

    const summary = stepSummary(profile, 'trunk', 'def456', 'abc123');

    assert.match(summary, /size: 1 files, 2 changed lines; base commits merged in: 0; CI failed checks: none/);
    assert.doesNotMatch(summary, /over cap/);
});
