/**
 * Tells the people behind a merged pull request that one of its backports did not land.
 * Without this the failed cherry-pick is only a red job in Actions that nobody is watching, and
 * the backport silently goes missing. The comment goes on the original pull request and on the
 * issue the backport workflow reopened for it, which is where the ticket owner looks.
 */

type Repository = { owner: string; repo: string };

export type IssueRef = Repository & { number: number };

export type FailureDetails = {
    targetBranch: string;
    pullRequest: IssueRef;
    runUrl: string;
    /** The merger first, then the assignees; duplicates and empty logins are dropped. */
    mentions: string[];
    /** The reopened issue; `undefined` when the pull request has none. */
    linkedIssue?: IssueRef;
};

type IssuesClient = {
    rest: {
        issues: {
            createComment(options: Repository & { issue_number: number; body: string }): Promise<unknown>;
        };
    };
};

export function buildComment({ targetBranch, pullRequest, runUrl, mentions }: FailureDetails): string {
    const logins = [...new Set(mentions.filter(Boolean))];

    return [
        `:x: Backport of #${pullRequest.number} to \`${targetBranch}\` failed, most likely due to merge conflicts. Please backport it manually.`,
        `Workflow run: ${runUrl}`,
        ...(logins.length > 0 ? [`cc ${logins.map((login) => `@${login}`).join(' ')}`] : []),
    ].join('\n\n');
}

export async function notifyBackportFailure(github: IssuesClient, details: FailureDetails): Promise<void> {
    const body = buildComment(details);

    for (const target of [details.pullRequest, details.linkedIssue]) {
        if (target === undefined) {
            continue;
        }

        await github.rest.issues.createComment({ owner: target.owner, repo: target.repo, issue_number: target.number, body });
    }
}
