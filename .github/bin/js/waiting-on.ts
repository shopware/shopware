/**
 * Decide who an open pull request is waiting for: its author, us, or nobody.
 *
 * This module only reports. It writes no labels and posts no comments — the point is to
 * agree on the rule before anything acts on it.
 *
 * ## Why not `updated_at`
 *
 * The obvious measure of a stalled pull request is how long ago it was updated, and it is
 * wrong. `updated_at` moves for label and milestone edits, reviewer changes, base-branch
 * retargeting and bot comments, so automation that reads it measures its own noise. When
 * the milestone rotation swapped `milestone/6.7.14.0` for `milestone/6.7.15.0` it touched
 * 71 of 273 open pull requests in one pass; #5420 reads as updated that day and had last
 * seen a human in February 2025. Nothing below reads `updated_at`.
 *
 * Activity here means a human moved the pull request forward:
 *
 *   counts                                   does not count
 *   -----------------------------------      ---------------------------------------
 *   a commit on the head branch              label and milestone changes
 *   an issue comment                         assignee and reviewer changes
 *   a submitted review                       base-branch retargeting, backport pushes
 *   marking ready for review                 CI re-runs
 *   converting to draft                      anything posted by a bot
 *
 * Inline review comments arrive in two shapes and both have to be read. Remarks submitted
 * together become one `PullRequestReview` in the timeline — four of them on #17314 are a
 * single item. A lone reply into an existing thread becomes none, and the timeline does
 * not show it at all: on #16259 that hid the author answering on 21 July and dated the
 * pull request three months earlier than it is. `reviewThreads` is the second lookup.
 *
 * Bots are recognised by `author.__typename == 'Bot'`, which covers codecov,
 * explore-openapi, github-actions and the octo-sts apps. Two cases that check cannot
 * reach are covered by `NON_HUMAN_LOGINS` instead: service accounts GitHub reports as a
 * plain `User`, and commit authors, where the field is typed `User` whoever pushed.
 *
 * ## The rule
 *
 * First match wins. The reason in brackets is what a later reminder would say, and it
 * matters as much as the direction: "we approved this, it needs a rebase" and "changes
 * requested, are you still on this?" both mean `author` and read nothing alike.
 *
 *   0. draft, converted by someone other than the author  -> author    (parked-by-us)
 *   1. draft                                              -> nobody    (wip-draft)
 *   2. carries `lifecycle/DoNotClose`                     -> nobody    (parked-by-label)
 *   3. review decision CHANGES_REQUESTED
 *        no author activity since that review             -> author    (changes-requested)
 *        author has answered it                           -> shopware  (re-review-pending)
 *   4. review decision APPROVED
 *        head conflicts with the base branch              -> author    (needs-rebase)
 *        mergeability not computed yet                    -> shopware  (mergeability-unknown)
 *        otherwise                                        -> shopware  (just-merge-it)
 *   5. at least one approval, decision still open         -> shopware  (second-review-missing)
 *   6. no review at all, ever                             -> shopware  (never-reviewed)
 *   7. a maintainer acted after the author last did       -> author    (author-turn)
 *   8. otherwise                                          -> shopware  (our-turn)
 *
 * Step 3 is the one that carries the rule. `reviewDecision` alone gets it backwards in
 * both directions: on #11516 a maintainer requested changes on 26 May and the author
 * answered on 28 May, so the decision still reads CHANGES_REQUESTED while the ball is
 * ours; on #17314 the author last touched it in June and a maintainer asked for changes
 * on 1 September. Same field value, opposite answers — only the timestamp of the last
 * still-blocking review separates them.
 *
 * Step 5 exists because `reviewDecision` is not an approval count. `trunk` requires two
 * approvals, so one approval reads as REVIEW_REQUIRED, indistinguishable from a pull
 * request nobody has opened. That is the state #9265 (approved 12 May 2025) and #19124
 * have been sitting in, and it is invisible in the GitHub UI's own filters.
 *
 * Unknowns resolve towards us, never towards the contributor: a pull request we cannot
 * classify lands in `shopware` so the worst case is that we look at it again.
 *
 * ## Age
 *
 * `since` is when the direction last flipped, not when the pull request was last touched,
 * and it is derived from the timeline on every run rather than stored. Nothing to drift,
 * and a verdict stays reproducible for any past date — which is what matters the first
 * time a team disputes one.
 *
 * Where the exact flip is not observable the later timestamp is used, so `since` is a
 * lower bound and the age it yields is conservative. `needs-rebase` is the clearest case:
 * GitHub does not record when a branch started conflicting, so the approval stands in.
 *
 * ## Two API traps
 *
 * `mergeable` is computed lazily. The first query for a pull request returns `UNKNOWN`
 * and a second one, moments later, the real value — all 45 external pull requests came
 * back `UNKNOWN` on a cold cache and none did on the retry. `resolveMergeability` does
 * that second pass for the pull requests whose verdict depends on it.
 *
 * `authorAssociation` reports public org membership. It is the raw GitHub value here and
 * is not read by the rule; a colleague whose membership is private shows up as
 * CONTRIBUTOR, which is fine for a report and not good enough to route a reminder on.
 */

export type WaitingOn = 'author' | 'shopware' | 'nobody';

export type WaitingOnReason =
    | 'parked-by-us'
    | 'wip-draft'
    | 'parked-by-label'
    | 'changes-requested'
    | 're-review-pending'
    | 'needs-rebase'
    | 'mergeability-unknown'
    | 'just-merge-it'
    | 'second-review-missing'
    | 'never-reviewed'
    | 'author-turn'
    | 'our-turn';

/** What stage 3 would set. Defined here so the report shows the label it would apply; nothing writes it yet. */
export const WAITING_ON_LABEL: Record<WaitingOn, string> = {
    author: 'waiting-on/author',
    shopware: 'waiting-on/shopware',
    nobody: 'waiting-on/nobody',
};

/** Reused rather than invented: the label already means "leave this one alone". */
export const PARKED_LABEL = 'lifecycle/DoNotClose';

/**
 * Accounts `__typename` does not mark as a `Bot`: service accounts that post as a plain
 * `User`, and every bot that appears as a commit author, where the field is typed `User`.
 * A missing entry makes a bot look like a reviewer and flips a verdict, which is why
 * `reportWaitingOn` logs every login it read as a person — the first runs are how the
 * gaps show up.
 */
export const NON_HUMAN_LOGINS = [
    'CLAassistant',
    'Copilot',
    'codecov',
    'cursoragent',
    'dependabot',
    'explore-openapi',
    'github-actions',
    'octo-sts',
    'shopwareBot',
    'shopware-octo-sts-app',
    'shopware-octo-sts-app-2',
];

export type ReviewDecision = 'APPROVED' | 'CHANGES_REQUESTED' | 'REVIEW_REQUIRED';

export type Mergeable = 'MERGEABLE' | 'CONFLICTING' | 'UNKNOWN';

/** Timestamps of the last event of each kind, all ISO 8601 in UTC so string order is chronological. */
export type Activity = {
    lastAuthorActivityAt?: string;
    lastMaintainerActivityAt?: string;
    /** The newest review that still blocks, i.e. was not superseded by a later one from the same reviewer. */
    lastChangesRequestedAt?: string;
    /** Set only when someone other than the author converted the pull request to a draft. */
    draftedByMaintainerAt?: string;
    readyForReviewAt?: string;
};

export type PullRequestFacts = Activity & {
    createdAt: string;
    isDraft: boolean;
    isParked: boolean;
    reviewDecision: ReviewDecision | null;
    mergeable: Mergeable;
    approvals: number;
    reviewCount: number;
};

export type WaitingOnVerdict = {
    waitingOn: WaitingOn;
    reason: WaitingOnReason;
    message: string;
    /** When the direction last flipped; a lower bound where the flip is not observable. */
    since: string;
};

function latest(...timestamps: (string | undefined)[]): string | undefined {
    return timestamps.filter((timestamp): timestamp is string => timestamp !== undefined).sort().at(-1);
}

export function classifyPullRequest(facts: PullRequestFacts): WaitingOnVerdict {
    const { createdAt, lastAuthorActivityAt, lastMaintainerActivityAt } = facts;

    if (facts.isDraft && facts.draftedByMaintainerAt) {
        return {
            waitingOn: 'author',
            reason: 'parked-by-us',
            message: 'we converted it to a draft, so it is back with the author',
            since: facts.draftedByMaintainerAt,
        };
    }

    if (facts.isDraft) {
        return {
            waitingOn: 'nobody',
            reason: 'wip-draft',
            message: 'is a draft the author has not offered for review',
            since: lastAuthorActivityAt ?? createdAt,
        };
    }

    if (facts.isParked) {
        return {
            waitingOn: 'nobody',
            reason: 'parked-by-label',
            message: `carries \`${PARKED_LABEL}\``,
            since: latest(lastAuthorActivityAt, lastMaintainerActivityAt) ?? createdAt,
        };
    }

    if (facts.reviewDecision === 'CHANGES_REQUESTED') {
        const blockedAt = facts.lastChangesRequestedAt;

        if (blockedAt && lastAuthorActivityAt && lastAuthorActivityAt > blockedAt) {
            return {
                waitingOn: 'shopware',
                reason: 're-review-pending',
                message: 'the author answered the requested changes and nobody has looked again',
                since: lastAuthorActivityAt,
            };
        }

        return {
            waitingOn: 'author',
            reason: 'changes-requested',
            message: 'changes were requested and the author has not acted since',
            since: blockedAt ?? lastMaintainerActivityAt ?? createdAt,
        };
    }

    if (facts.reviewDecision === 'APPROVED') {
        if (facts.mergeable === 'CONFLICTING') {
            return {
                waitingOn: 'author',
                reason: 'needs-rebase',
                message: 'is approved but conflicts with its base branch',
                since: lastMaintainerActivityAt ?? createdAt,
            };
        }

        if (facts.mergeable === 'UNKNOWN') {
            return {
                waitingOn: 'shopware',
                reason: 'mergeability-unknown',
                message: 'is approved, and GitHub has not computed whether it still merges',
                since: lastMaintainerActivityAt ?? createdAt,
            };
        }

        return {
            waitingOn: 'shopware',
            reason: 'just-merge-it',
            message: 'is approved and merges cleanly',
            since: lastMaintainerActivityAt ?? createdAt,
        };
    }

    if (facts.approvals >= 1) {
        return {
            waitingOn: 'shopware',
            reason: 'second-review-missing',
            message: `has ${facts.approvals} approval(s) and is short of the required review count`,
            since: lastMaintainerActivityAt ?? createdAt,
        };
    }

    if (facts.reviewCount === 0) {
        return {
            waitingOn: 'shopware',
            reason: 'never-reviewed',
            message: 'has not been reviewed once',
            since: facts.readyForReviewAt ?? createdAt,
        };
    }

    if (lastMaintainerActivityAt && (!lastAuthorActivityAt || lastMaintainerActivityAt > lastAuthorActivityAt)) {
        return {
            waitingOn: 'author',
            reason: 'author-turn',
            message: 'a maintainer wrote last',
            since: lastMaintainerActivityAt,
        };
    }

    return {
        waitingOn: 'shopware',
        reason: 'our-turn',
        message: 'the author wrote last',
        since: lastAuthorActivityAt ?? createdAt,
    };
}

export function daysSince(timestamp: string, now: Date): number {
    return Math.floor((now.getTime() - new Date(timestamp).getTime()) / 86_400_000);
}

export type TimelineEvent = {
    kind: 'comment' | 'review' | 'commit' | 'ready-for-review' | 'convert-to-draft';
    at: string;
    /** Absent when GitHub cannot resolve the actor, e.g. a commit from an address with no account. */
    actor?: string;
    isBot?: boolean;
};

export type OpinionatedReview = {
    state: string;
    submittedAt: string;
};

/**
 * A `User`-typed field keeps the suffix GitHub strips from the `Bot` type, so a commit
 * pushed by an app reads as `dependabot[bot]` where its comments read as `dependabot`.
 */
export function isNonHumanLogin(login: string): boolean {
    return login.endsWith('[bot]') || NON_HUMAN_LOGINS.includes(login);
}

export function isBotActor(event: TimelineEvent): boolean {
    return event.isBot === true || (event.actor !== undefined && isNonHumanLogin(event.actor));
}

/** A commit is the one event worth keeping without a resolvable actor — see `summarizeActivity`. */
export function isHuman(event: TimelineEvent): boolean {
    return !isBotActor(event) && (event.kind === 'commit' || event.actor !== undefined);
}

/**
 * Reduce a timeline to the timestamps the rule needs.
 *
 * A commit counts as author activity whoever authored it: on a fork the author owns the
 * branch, and a maintainer pushing there is rare enough not to model. It also counts when
 * GitHub cannot resolve the committer to an account, which happens for an address with no
 * user attached and would otherwise silently drop a push.
 *
 * `committedDate` is the commit's own date, so a force-push of untouched history reads as
 * old activity — a rebase rewrites it, a replayed branch does not.
 */
export function summarizeActivity(events: TimelineEvent[], latestOpinionatedReviews: OpinionatedReview[], authorLogin?: string): Activity {
    const activity: Activity = {};

    for (const event of events.filter(isHuman)) {
        const byAuthor = event.kind === 'commit' || event.actor === authorLogin;

        if (byAuthor) {
            activity.lastAuthorActivityAt = latest(activity.lastAuthorActivityAt, event.at);
        } else {
            activity.lastMaintainerActivityAt = latest(activity.lastMaintainerActivityAt, event.at);
        }

        if (event.kind === 'ready-for-review') {
            activity.readyForReviewAt = latest(activity.readyForReviewAt, event.at);
        }

        if (event.kind === 'convert-to-draft' && event.actor !== authorLogin) {
            activity.draftedByMaintainerAt = latest(activity.draftedByMaintainerAt, event.at);
        }
    }

    // `latestOpinionatedReviews` already drops a review its author superseded, so anything
    // left at CHANGES_REQUESTED still blocks.
    activity.lastChangesRequestedAt = latest(
        ...latestOpinionatedReviews.filter((review) => review.state === 'CHANGES_REQUESTED').map((review) => review.submittedAt),
    );

    return activity;
}

const OPEN_PULL_REQUESTS_QUERY = `
    query($owner: String!, $repo: String!, $after: String) {
        repository(owner: $owner, name: $repo) {
            pullRequests(states: OPEN, first: 15, after: $after) {
                pageInfo { hasNextPage endCursor }
                nodes {
                    number
                    title
                    url
                    createdAt
                    isDraft
                    authorAssociation
                    reviewDecision
                    mergeable
                    author { login __typename }
                    labels(first: 50) { nodes { name } }
                    approvals: reviews(states: APPROVED) { totalCount }
                    allReviews: reviews(first: 1) { totalCount }
                    latestOpinionatedReviews(first: 20) { nodes { state submittedAt } }
                    reviewThreads(last: 50) { nodes { comments(last: 3) { nodes { createdAt author { login __typename } } } } }
                    timelineItems(last: 40, itemTypes: [ISSUE_COMMENT, PULL_REQUEST_REVIEW, PULL_REQUEST_COMMIT, READY_FOR_REVIEW_EVENT, CONVERT_TO_DRAFT_EVENT]) {
                        nodes {
                            __typename
                            ... on IssueComment { createdAt author { login __typename } }
                            ... on PullRequestReview { submittedAt author { login __typename } }
                            ... on PullRequestCommit { commit { committedDate author { user { login } } } }
                            ... on ReadyForReviewEvent { createdAt actor { login __typename } }
                            ... on ConvertToDraftEvent { createdAt actor { login __typename } }
                        }
                    }
                }
            }
        }
    }
`;

const MERGEABILITY_QUERY = `
    query($owner: String!, $repo: String!, $number: Int!) {
        repository(owner: $owner, name: $repo) {
            pullRequest(number: $number) { mergeable }
        }
    }
`;

type TimelineNode = {
    __typename: string;
    createdAt?: string;
    submittedAt?: string;
    author?: { login: string; __typename: string } | null;
    actor?: { login: string; __typename: string } | null;
    commit?: { committedDate: string; author?: { user?: { login: string } | null } | null };
};

type ReviewThreadNode = {
    comments: { nodes: { createdAt: string; author?: { login: string; __typename: string } | null }[] };
};

type PullRequestNode = {
    number: number;
    title: string;
    url: string;
    createdAt: string;
    isDraft: boolean;
    authorAssociation: string;
    reviewDecision: ReviewDecision | null;
    mergeable: Mergeable;
    author: { login: string; __typename: string } | null;
    labels: { nodes: { name: string }[] };
    approvals: { totalCount: number };
    allReviews: { totalCount: number };
    latestOpinionatedReviews: { nodes: OpinionatedReview[] };
    reviewThreads: { nodes: ReviewThreadNode[] };
    timelineItems: { nodes: TimelineNode[] };
};

const TIMELINE_KINDS: Record<string, TimelineEvent['kind']> = {
    IssueComment: 'comment',
    PullRequestReview: 'review',
    PullRequestCommit: 'commit',
    ReadyForReviewEvent: 'ready-for-review',
    ConvertToDraftEvent: 'convert-to-draft',
};

export function toTimelineEvents(nodes: TimelineNode[]): TimelineEvent[] {
    const events: TimelineEvent[] = [];

    for (const node of nodes) {
        const kind = TIMELINE_KINDS[node.__typename];
        const at = node.createdAt ?? node.submittedAt ?? node.commit?.committedDate;
        if (!kind || !at) {
            continue;
        }

        const actor = node.author ?? node.actor;
        events.push({
            kind,
            at,
            actor: actor?.login ?? node.commit?.author?.user?.login,
            // A commit carries no app identity, so an unresolved committer is a person, not a bot.
            isBot: actor?.__typename === 'Bot',
        });
    }

    return events;
}

/**
 * The replies the timeline does not carry. Only the newest few per thread are read: the
 * question is who spoke last, not the whole conversation. A reply into a thread older than
 * the 50 the query asks for is the one case this still misses.
 */
export function toReviewThreadEvents(nodes: ReviewThreadNode[]): TimelineEvent[] {
    return nodes.flatMap((thread) =>
        thread.comments.nodes.map((comment) => ({
            kind: 'comment' as const,
            at: comment.createdAt,
            actor: comment.author?.login,
            isBot: comment.author?.__typename === 'Bot',
        })),
    );
}

export function factsOf(node: PullRequestNode): PullRequestFacts {
    const events = [...toTimelineEvents(node.timelineItems.nodes), ...toReviewThreadEvents(node.reviewThreads.nodes)];

    return {
        ...summarizeActivity(events, node.latestOpinionatedReviews.nodes, node.author?.login),
        createdAt: node.createdAt,
        isDraft: node.isDraft,
        isParked: node.labels.nodes.some((label) => label.name === PARKED_LABEL),
        reviewDecision: node.reviewDecision,
        mergeable: node.mergeable,
        approvals: node.approvals.totalCount,
        reviewCount: node.allReviews.totalCount,
    };
}

type GraphqlClient = {
    graphql<T>(query: string, variables: Record<string, unknown>): Promise<T>;
};

type Core = {
    info(message: string): void;
    warning(message: string): void;
    setOutput(name: string, value: string): void;
    summary: {
        addRaw(text: string, addEOL?: boolean): unknown;
        write(): Promise<unknown>;
    };
};

type Context = {
    repo: { owner: string; repo: string };
};

async function fetchOpenPullRequests(github: GraphqlClient, repo: Context['repo']): Promise<PullRequestNode[]> {
    const nodes: PullRequestNode[] = [];
    let after: string | undefined = undefined;

    do {
        const result: {
            repository: {
                pullRequests: {
                    pageInfo: { hasNextPage: boolean; endCursor: string | null };
                    nodes: PullRequestNode[];
                };
            };
        } = await github.graphql(OPEN_PULL_REQUESTS_QUERY, { ...repo, after });

        const { pageInfo, nodes: page } = result.repository.pullRequests;
        nodes.push(...page);
        after = pageInfo.hasNextPage ? pageInfo.endCursor ?? undefined : undefined;
    } while (after);

    return nodes;
}

/**
 * Ask again for the pull requests whose verdict turns on `mergeable`, which GitHub only
 * computes once something has asked for it. The first query above is that ask.
 */
export async function resolveMergeability(github: GraphqlClient, core: Core, repo: Context['repo'], nodes: PullRequestNode[]): Promise<void> {
    const pending = nodes.filter((node) => node.mergeable === 'UNKNOWN' && node.reviewDecision === 'APPROVED');
    if (pending.length === 0) {
        return;
    }

    core.info(`Re-reading mergeability for ${pending.length} approved pull request(s).`);

    for (const node of pending) {
        try {
            const result: { repository: { pullRequest: { mergeable: Mergeable } } } = await github.graphql(MERGEABILITY_QUERY, { ...repo, number: node.number });
            node.mergeable = result.repository.pullRequest.mergeable;
        } catch (error) {
            // Leaving it UNKNOWN keeps the pull request on our side of the report, which is the safe direction.
            core.warning(`Could not re-read mergeability for #${node.number}: ${error instanceof Error ? error.message : String(error)}`);
        }
    }
}

export type Row = {
    number: number;
    title: string;
    url: string;
    author: string;
    authorAssociation: string;
    waitingOn: WaitingOn;
    reason: WaitingOnReason;
    label: string;
    since: string;
    days: number;
};

/**
 * Dependabot and the octo-sts apps open pull requests of their own; a report about who
 * owes a response has nothing to say about them.
 */
function isHumanAuthored(node: PullRequestNode): node is PullRequestNode & { author: { login: string; __typename: string } } {
    return node.author !== null && node.author.__typename !== 'Bot' && !isNonHumanLogin(node.author.login);
}

export function buildRows(nodes: PullRequestNode[], now: Date): Row[] {
    return nodes
        .filter(isHumanAuthored)
        .map((node) => {
            const verdict = classifyPullRequest(factsOf(node));

            return {
                number: node.number,
                title: node.title,
                url: node.url,
                author: node.author.login,
                authorAssociation: node.authorAssociation,
                waitingOn: verdict.waitingOn,
                reason: verdict.reason,
                label: WAITING_ON_LABEL[verdict.waitingOn],
                since: verdict.since,
                days: daysSince(verdict.since, now),
            };
        })
        .sort((left, right) => right.days - left.days);
}

const BUCKET_ORDER: WaitingOn[] = ['shopware', 'author', 'nobody'];

export function renderReport(rows: Row[]): string {
    const lines: string[] = ['## Who is waiting for whom', ''];

    const byReason = new Map<string, number>();
    for (const row of rows) {
        byReason.set(row.reason, (byReason.get(row.reason) ?? 0) + 1);
    }

    lines.push(`${rows.length} open pull request(s), bots excluded.`, '');
    lines.push('| reason | count |', '| --- | --: |');
    for (const [reason, count] of [...byReason].sort((left, right) => right[1] - left[1])) {
        lines.push(`| \`${reason}\` | ${count} |`);
    }
    lines.push('');

    for (const bucket of BUCKET_ORDER) {
        const bucketRows = rows.filter((row) => row.waitingOn === bucket);
        lines.push(`### ${WAITING_ON_LABEL[bucket]} — ${bucketRows.length}`, '');

        if (bucketRows.length === 0) {
            lines.push('None.', '');
            continue;
        }

        lines.push('| PR | author | association | reason | days |', '| --- | --- | --- | --- | --: |');
        for (const row of bucketRows) {
            lines.push(`| [#${row.number}](${row.url}) | ${row.author} | ${row.authorAssociation} | \`${row.reason}\` | ${row.days} |`);
        }
        lines.push('');
    }

    return lines.join('\n');
}

/**
 * Nothing here is labelled, commented on or closed. The `rows` output carries the verdicts
 * as JSON so a later stage can consume them without this having to change.
 */
export async function reportWaitingOn({ github, core, context }: { github: GraphqlClient; core: Core; context: Context }): Promise<void> {
    const nodes = await fetchOpenPullRequests(github, context.repo);
    core.info(`Read ${nodes.length} open pull request(s).`);

    await resolveMergeability(github, core, context.repo, nodes);

    const rows = buildRows(nodes, new Date());

    // Every login that was read as a person. A service account in here is a gap in
    // NON_HUMAN_LOGINS, and it is cheaper to spot now than after a reminder went out.
    const humans = new Set<string>();
    for (const node of nodes) {
        const events = [...toTimelineEvents(node.timelineItems.nodes), ...toReviewThreadEvents(node.reviewThreads.nodes)];
        for (const event of events.filter(isHuman)) {
            if (event.actor !== undefined) {
                humans.add(event.actor);
            }
        }
    }
    core.info(`Counted as human: ${[...humans].sort().join(', ')}`);

    core.summary.addRaw(renderReport(rows));
    await core.summary.write();
    core.setOutput('rows', JSON.stringify(rows));
}
