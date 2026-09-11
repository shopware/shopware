import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    buildRows,
    classifyPullRequest,
    daysSince,
    isHuman,
    isNonHumanLogin,
    NON_HUMAN_LOGINS,
    PARKED_LABEL,
    type PullRequestFacts,
    summarizeActivity,
    type TimelineEvent,
    toReviewThreadEvents,
    toTimelineEvents,
    WAITING_ON_LABEL,
} from './waiting-on.ts';

const BASE: PullRequestFacts = {
    createdAt: '2026-06-01T00:00:00Z',
    isDraft: false,
    isParked: false,
    reviewDecision: 'REVIEW_REQUIRED',
    mergeable: 'MERGEABLE',
    approvals: 0,
    reviewCount: 0,
};

const classify = (facts: Partial<PullRequestFacts>) => classifyPullRequest({ ...BASE, ...facts });

test('a draft a maintainer parked is back with the author', () => {
    // Regression: this is #17689, which a maintainer converted to a draft on 2026-07-20
    // after a month of silence.
    const verdict = classify({
        isDraft: true,
        draftedByMaintainerAt: '2026-07-20T10:00:00Z',
        lastAuthorActivityAt: '2026-07-07T10:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'author');
    assert.equal(verdict.reason, 'parked-by-us');
    assert.equal(verdict.since, '2026-07-20T10:00:00Z');
});

test('a draft that is still the authors own work waits for nobody', () => {
    const verdict = classify({ isDraft: true, lastAuthorActivityAt: '2026-08-01T00:00:00Z' });

    assert.equal(verdict.waitingOn, 'nobody');
    assert.equal(verdict.reason, 'wip-draft');
});

test(`\`${PARKED_LABEL}\` takes a pull request out of the report`, () => {
    const verdict = classify({ isParked: true, reviewDecision: 'CHANGES_REQUESTED', lastChangesRequestedAt: '2026-06-02T00:00:00Z' });

    assert.equal(verdict.waitingOn, 'nobody');
    assert.equal(verdict.reason, 'parked-by-label');
});

test('changes requested and no answer is the author', () => {
    // Regression: this is #17314, where the author last pushed in June and a maintainer
    // requested changes on 2026-09-01.
    const verdict = classify({
        reviewDecision: 'CHANGES_REQUESTED',
        reviewCount: 1,
        lastAuthorActivityAt: '2026-06-08T15:33:02Z',
        lastMaintainerActivityAt: '2026-09-01T09:00:34Z',
        lastChangesRequestedAt: '2026-09-01T09:00:34Z',
    });

    assert.equal(verdict.waitingOn, 'author');
    assert.equal(verdict.reason, 'changes-requested');
    assert.equal(verdict.since, '2026-09-01T09:00:34Z');
});

test('changes requested but answered is ours again, though the decision still says otherwise', () => {
    // Regression: this is #11516. A maintainer requested changes on 2026-05-26, the author
    // answered on 2026-05-28, and `reviewDecision` reads CHANGES_REQUESTED to this day.
    const verdict = classify({
        reviewDecision: 'CHANGES_REQUESTED',
        reviewCount: 2,
        approvals: 1,
        lastChangesRequestedAt: '2026-05-26T00:00:00Z',
        lastMaintainerActivityAt: '2026-05-26T00:00:00Z',
        lastAuthorActivityAt: '2026-05-28T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 're-review-pending');
    assert.equal(verdict.since, '2026-05-28T00:00:00Z');
});

test('an approved pull request that conflicts needs the author', () => {
    // Regression: this is #18502, approved twice on 2026-07-22 and asked for a conflict fix.
    const verdict = classify({
        reviewDecision: 'APPROVED',
        reviewCount: 3,
        approvals: 2,
        mergeable: 'CONFLICTING',
        lastMaintainerActivityAt: '2026-07-22T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'author');
    assert.equal(verdict.reason, 'needs-rebase');
});

test('an approved pull request that merges cleanly is only waiting for us', () => {
    const verdict = classify({ reviewDecision: 'APPROVED', reviewCount: 2, approvals: 2, lastMaintainerActivityAt: '2026-08-01T00:00:00Z' });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 'just-merge-it');
});

test('unresolved mergeability lands on us rather than on the contributor', () => {
    const verdict = classify({ reviewDecision: 'APPROVED', reviewCount: 2, approvals: 2, mergeable: 'UNKNOWN' });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 'mergeability-unknown');
});

test('one approval short of the required count is ours, not an unreviewed pull request', () => {
    // Regression: this is #9265, approved on 2025-05-12 and never merged. `trunk` wants two
    // approvals, so the decision reads REVIEW_REQUIRED and hides it.
    const verdict = classify({
        reviewDecision: 'REVIEW_REQUIRED',
        reviewCount: 1,
        approvals: 1,
        lastMaintainerActivityAt: '2025-05-12T00:00:00Z',
        lastAuthorActivityAt: '2025-05-12T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 'second-review-missing');
    assert.equal(verdict.since, '2025-05-12T00:00:00Z');
});

test('a pull request nobody ever reviewed dates from when it was offered, not opened', () => {
    // Regression: this is #18872, 40 days open without a single review.
    const verdict = classify({
        createdAt: '2026-07-31T00:00:00Z',
        readyForReviewAt: '2026-08-02T00:00:00Z',
        lastAuthorActivityAt: '2026-07-31T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 'never-reviewed');
    assert.equal(verdict.since, '2026-08-02T00:00:00Z');
});

test('a maintainer writing last puts it back with the author', () => {
    const verdict = classify({
        reviewCount: 2,
        lastAuthorActivityAt: '2026-07-01T00:00:00Z',
        lastMaintainerActivityAt: '2026-07-30T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'author');
    assert.equal(verdict.reason, 'author-turn');
});

test('the author writing last leaves it with us', () => {
    const verdict = classify({
        reviewCount: 2,
        lastAuthorActivityAt: '2026-07-30T00:00:00Z',
        lastMaintainerActivityAt: '2026-07-01T00:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'shopware');
    assert.equal(verdict.reason, 'our-turn');
});

test('timestamps decide, so a same-day flip turns on the time of day', () => {
    const ours = classify({ reviewCount: 2, lastAuthorActivityAt: '2026-07-01T12:00:00Z', lastMaintainerActivityAt: '2026-07-01T09:00:00Z' });

    assert.equal(ours.waitingOn, 'shopware');
    assert.equal(ours.reason, 'our-turn');
});

test('an author who withdrew reads as the authors turn, which is the documented blind spot', () => {
    // This is #5420: the author gave up on 2025-02-04 and a maintainer answered
    // "we will take over it" three hours later. The rule reads the order of events, not
    // the sentences, so it says `author` where a person says `shopware`. Such a pull
    // request needs the label, not a cleverer rule.
    const verdict = classify({
        reviewCount: 2,
        lastAuthorActivityAt: '2025-02-04T09:00:00Z',
        lastMaintainerActivityAt: '2025-02-04T12:00:00Z',
    });

    assert.equal(verdict.waitingOn, 'author');
    assert.equal(classify({ isParked: true, reviewCount: 2 }).waitingOn, 'nobody');
});

test('the rule never reads updated_at, so a label sweep cannot move a verdict', () => {
    // The 2026-08-24 milestone rotation bumped `updated_at` on 71 of 273 open pull
    // requests. Nothing in PullRequestFacts can carry that.
    assert.ok(!Object.keys(BASE).some((key) => key.toLowerCase().includes('updated')));
});

test('daysSince counts whole days', () => {
    assert.equal(daysSince('2026-09-01T00:00:00Z', new Date('2026-09-10T12:00:00Z')), 9);
});

const event = (partial: Partial<TimelineEvent> & Pick<TimelineEvent, 'kind' | 'at'>): TimelineEvent => ({ ...partial });

test('summarizeActivity splits author from maintainer', () => {
    const activity = summarizeActivity(
        [
            event({ kind: 'commit', at: '2026-06-08T15:33:02Z', actor: 'M-arcus' }),
            event({ kind: 'review', at: '2026-09-01T09:00:34Z', actor: 'umutdogan4291' }),
        ],
        [{ state: 'CHANGES_REQUESTED', submittedAt: '2026-09-01T09:00:34Z' }],
        'M-arcus',
    );

    assert.equal(activity.lastAuthorActivityAt, '2026-06-08T15:33:02Z');
    assert.equal(activity.lastMaintainerActivityAt, '2026-09-01T09:00:34Z');
    assert.equal(activity.lastChangesRequestedAt, '2026-09-01T09:00:34Z');
});

test('bot comments are not maintainer activity', () => {
    const activity = summarizeActivity(
        [
            event({ kind: 'commit', at: '2026-06-08T15:33:02Z', actor: 'M-arcus' }),
            event({ kind: 'comment', at: '2026-06-08T15:43:35Z', actor: 'github-actions', isBot: true }),
            event({ kind: 'comment', at: '2026-06-08T15:46:13Z', actor: 'codecov', isBot: true }),
        ],
        [],
        'M-arcus',
    );

    assert.equal(activity.lastMaintainerActivityAt, undefined);
});

test('a service account posting as a plain User is not maintainer activity either', () => {
    // GitHub reports CLAassistant as a `User`, so only the login list catches it.
    assert.ok(NON_HUMAN_LOGINS.includes('CLAassistant'));

    const activity = summarizeActivity([event({ kind: 'comment', at: '2026-07-31T00:00:00Z', actor: 'CLAassistant' })], [], 'GerDner');

    assert.equal(activity.lastMaintainerActivityAt, undefined);
});

test('a commit whose committer GitHub cannot resolve still counts as author activity', () => {
    const activity = summarizeActivity([event({ kind: 'commit', at: '2026-08-01T00:00:00Z' })], [], 'someone');

    assert.equal(activity.lastAuthorActivityAt, '2026-08-01T00:00:00Z');
});

test('a comment with no resolvable author is dropped rather than counted', () => {
    assert.equal(isHuman(event({ kind: 'comment', at: '2026-08-01T00:00:00Z' })), false);
});

test('a review superseded by its author no longer blocks', () => {
    // latestOpinionatedReviews only returns the newest opinion per reviewer, so an
    // approval that followed a rejection leaves nothing at CHANGES_REQUESTED.
    const activity = summarizeActivity([], [{ state: 'APPROVED', submittedAt: '2026-08-01T00:00:00Z' }], 'someone');

    assert.equal(activity.lastChangesRequestedAt, undefined);
});

test('the author converting their own pull request to a draft is not us parking it', () => {
    const activity = summarizeActivity([event({ kind: 'convert-to-draft', at: '2026-08-01T00:00:00Z', actor: 'author' })], [], 'author');

    assert.equal(activity.draftedByMaintainerAt, undefined);
    assert.equal(activity.lastAuthorActivityAt, '2026-08-01T00:00:00Z');
});

test('toTimelineEvents reads each shape GitHub returns', () => {
    const events = toTimelineEvents([
        { __typename: 'IssueComment', createdAt: '2026-01-01T00:00:00Z', author: { login: 'codecov', __typename: 'Bot' } },
        { __typename: 'PullRequestReview', submittedAt: '2026-01-02T00:00:00Z', author: { login: 'mitelg', __typename: 'User' } },
        { __typename: 'PullRequestCommit', commit: { committedDate: '2026-01-03T00:00:00Z', author: { user: { login: 'aragon999' } } } },
        { __typename: 'ConvertToDraftEvent', createdAt: '2026-01-04T00:00:00Z', actor: { login: 'marcelbrode', __typename: 'User' } },
        { __typename: 'LabeledEvent', createdAt: '2026-01-05T00:00:00Z' },
    ]);

    assert.deepEqual(
        events.map(({ kind, actor, isBot }) => ({ kind, actor, isBot })),
        [
            { kind: 'comment', actor: 'codecov', isBot: true },
            { kind: 'review', actor: 'mitelg', isBot: false },
            { kind: 'commit', actor: 'aragon999', isBot: false },
            { kind: 'convert-to-draft', actor: 'marcelbrode', isBot: false },
        ],
    );
});

const node = (overrides: Record<string, unknown> = {}) => ({
    number: 1,
    title: 'a pull request',
    url: 'https://github.com/shopware/shopware/pull/1',
    createdAt: '2026-08-01T00:00:00Z',
    isDraft: false,
    authorAssociation: 'CONTRIBUTOR',
    reviewDecision: null,
    mergeable: 'MERGEABLE',
    author: { login: 'someone', __typename: 'User' },
    labels: { nodes: [] },
    approvals: { totalCount: 0 },
    allReviews: { totalCount: 0 },
    latestOpinionatedReviews: { nodes: [] },
    reviewThreads: { nodes: [] },
    timelineItems: { nodes: [] },
    ...overrides,
}) as Parameters<typeof buildRows>[0][number];

test('buildRows drops bot-authored pull requests and sorts the oldest first', () => {
    const rows = buildRows(
        [
            node({ number: 10, createdAt: '2026-09-01T00:00:00Z' }),
            node({ number: 11, author: { login: 'dependabot', __typename: 'Bot' } }),
            node({ number: 12, createdAt: '2026-01-01T00:00:00Z' }),
            node({ number: 13, author: { login: 'shopware-octo-sts-app-2', __typename: 'User' } }),
        ],
        new Date('2026-09-10T00:00:00Z'),
    );

    assert.deepEqual(rows.map((row) => row.number), [12, 10]);
    assert.equal(rows[0].label, WAITING_ON_LABEL.shopware);
    assert.equal(rows[0].days, 252);
});

test('an app pushing a commit is not the author working, whatever the field type says', () => {
    // The live run turned up `dependabot[bot]`, `Copilot`, `cursoragent` and `shopwareBot`
    // among the logins counted as people; the suffix rule and the list cover them.
    assert.equal(isNonHumanLogin('dependabot[bot]'), true);
    assert.equal(isNonHumanLogin('Copilot'), true);
    assert.equal(isNonHumanLogin('cursoragent'), true);
    assert.equal(isNonHumanLogin('shopwareBot'), true);
    assert.equal(isNonHumanLogin('mitelg'), false);

    const activity = summarizeActivity([event({ kind: 'commit', at: '2026-08-01T00:00:00Z', actor: 'dependabot[bot]' })], [], 'someone');

    assert.equal(activity.lastAuthorActivityAt, undefined);
});

test('a lone reply inside a review thread is activity the timeline does not report', () => {
    // Regression: this is #16259. The author answered on 2026-07-21 with a single inline
    // reply, which produces no timeline item, and reading the timeline alone dated the
    // pull request to April.
    const events = toReviewThreadEvents([
        { comments: { nodes: [{ createdAt: '2026-07-21T09:00:00Z', author: { login: 'gecolay', __typename: 'User' } }] } },
        { comments: { nodes: [{ createdAt: '2026-07-21T12:24:31Z', author: { login: 'keulinho', __typename: 'User' } }] } },
    ]);

    const activity = summarizeActivity(events, [{ state: 'CHANGES_REQUESTED', submittedAt: '2026-04-28T07:55:58Z' }], 'gecolay');

    assert.equal(activity.lastAuthorActivityAt, '2026-07-21T09:00:00Z');
    assert.equal(activity.lastMaintainerActivityAt, '2026-07-21T12:24:31Z');
});
