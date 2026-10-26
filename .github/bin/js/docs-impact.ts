import { writeFileSync } from 'node:fs';

export const AUDIENCES = ['end-user', 'developer'] as const;

export type Audience = (typeof AUDIENCES)[number];

export const END_USER_DOCS_TEAM = '@shopware/pp-enduser-docs';

export const DOMAIN_TEAMS: Record<string, string> = {
    'domain/b2b': '@shopware/product-cc-b2b',
    'domain/checkout': '@shopware/product-cc-checkout',
    'domain/crm-after-sales': '@shopware/product-cc-after-sales',
    'domain/discovery': '@shopware/product-cc-discovery',
    'domain/framework': '@shopware/product-cc-framework',
    'domain/inventory': '@shopware/product-cc-inventory',
};

export const COMMENT_MARKER = '<!-- sw-docs-impact -->';

const MAX_SUMMARY_LENGTH = 20000;

const SKIPPED_COMMIT_TYPES = /^(test|ci|chore|docs|refactor)(\([^)]*\))?:/;

export function shouldCheck(eventName: string, commitMessage: string): boolean {
    if (eventName !== 'push') {
        return true;
    }

    return !SKIPPED_COMMIT_TYPES.test(commitMessage.trimStart());
}

type Repo = { owner: string; repo: string };

type GitHub = {
    rest: {
        repos: {
            listPullRequestsAssociatedWithCommit(params: Repo & { commit_sha: string }): Promise<{
                data: Array<{ number: number; merged_at: string | null; base: { ref: string } }>;
            }>;
        };
        pulls: {
            get(params: Repo & { pull_number: number; mediaType?: { format: string } }): Promise<{ data: unknown }>;
            listFiles(params: Repo & { pull_number: number; per_page: number }): Promise<{ data: unknown }>;
        };
        issues: {
            listComments(params: Repo & { issue_number: number; per_page: number }): Promise<{
                data: Array<{ id: number; body?: string }>;
            }>;
            createComment(params: Repo & { issue_number: number; body: string }): Promise<unknown>;
            updateComment(params: Repo & { comment_id: number; body: string }): Promise<unknown>;
        };
    };
    paginate<T>(method: unknown, params: object): Promise<T[]>;
};

type Core = {
    info(message: string): void;
    warning(message: string): void;
    setFailed(message: string): void;
    summary?: { addRaw(text: string, addEOL?: boolean): { write(): Promise<unknown> } };
};

type Context = {
    repo: Repo;
    sha: string;
    eventName: string;
    payload: { inputs?: { pr_number?: string | number; dry_run?: string | boolean } };
};

type AgentOutputItem = {
    type: string;
    audiences?: string;
    summary?: string;
};

export function parseAudiences(raw: string): Audience[] {
    const audiences = raw
        .split(',')
        .map((audience) => audience.trim())
        .filter((audience) => audience !== '');

    const unknown = audiences.filter((audience) => !(AUDIENCES as readonly string[]).includes(audience));
    if (unknown.length > 0) {
        throw new Error(`Unknown audience(s): ${unknown.join(', ')}. Allowed: ${AUDIENCES.join(', ')}`);
    }
    if (audiences.length === 0) {
        throw new Error('No audience given');
    }

    return [...new Set(audiences)] as Audience[];
}

export function neutraliseMentions(text: string): string {
    return text.replace(/(^|[^\w`])@([A-Za-z0-9][\w-]*(?:\/[\w.-]+)?)/g, '$1`@$2`');
}

export function owningTeams(labels: string[]): string[] {
    return [...new Set(labels.map((label) => DOMAIN_TEAMS[label]).filter((team) => team !== undefined))];
}

export function buildComment(audiences: Audience[], summary: string, labels: string[]): string {
    let body = neutraliseMentions(summary.trim());
    if (body.length > MAX_SUMMARY_LENGTH) {
        body = `${body.slice(0, MAX_SUMMARY_LENGTH)}\n\n_(truncated)_`;
    }

    const pings: string[] = [];
    if (audiences.includes('end-user')) {
        pings.push(`${END_USER_DOCS_TEAM}: please check the end-user documentation.`);
    }
    const owners = owningTeams(labels);
    if (audiences.includes('developer') && owners.length > 0) {
        pings.push(`${owners.join(' ')}: please consider a developer documentation update.`);
    }

    return [COMMENT_MARKER, '### Documentation impact', '', ...pings, ...(pings.length > 0 ? [''] : []), body].join('\n');
}

export function isDryRun(context: Context): boolean {
    const raw = context.payload.inputs?.dry_run;

    return raw === true || raw === 'true';
}

export function requestedPullRequestNumber(context: Context): number | null {
    const raw = context.payload.inputs?.pr_number;
    if (raw === undefined || raw === '') {
        return null;
    }

    const number = Number(raw);
    if (!Number.isInteger(number) || number <= 0) {
        throw new Error(`Invalid pr_number input: ${raw}`);
    }

    return number;
}

export async function resolvePullRequestNumber({ github, context }: { github: GitHub; context: Context }): Promise<number | null> {
    const requested = requestedPullRequestNumber(context);
    if (requested !== null) {
        return requested;
    }

    const { data } = await github.rest.repos.listPullRequestsAssociatedWithCommit({
        ...context.repo,
        commit_sha: context.sha,
    });

    return data.find((pr) => pr.merged_at !== null && pr.base.ref === 'trunk')?.number ?? null;
}

type PullRequest = {
    number: number;
    title: string;
    body: string | null;
    html_url: string;
    user: { login: string } | null;
    labels: Array<{ name: string }>;
    merged_at: string | null;
    base: { ref: string };
};

type PullRequestFile = {
    filename: string;
    status: string;
    additions: number;
    deletions: number;
    patch?: string;
};

export function summarisePullRequest(pr: PullRequest) {
    return {
        number: pr.number,
        title: pr.title,
        url: pr.html_url,
        author: pr.user?.login ?? null,
        labels: pr.labels.map((label) => label.name),
        merged_at: pr.merged_at,
        base: pr.base.ref,
        body: pr.body ?? '',
    };
}

export async function collectAgentInput({ github, context, core }: { github: GitHub; context: Context; core: Core }, dir: string): Promise<void> {
    const number = await resolvePullRequestNumber({ github, context });
    if (number === null) {
        core.info(`No merged pull request into trunk is associated with ${context.sha}`);
        writeFileSync(`${dir}/pr.json`, JSON.stringify({ pr: null }));
        return;
    }

    const params = { ...context.repo, pull_number: number };
    const { data } = await github.rest.pulls.get(params);
    const pr = summarisePullRequest(data as PullRequest);
    const files = (await github.paginate<PullRequestFile>(github.rest.pulls.listFiles, { ...params, per_page: 100 })).map(
        ({ filename, status, additions, deletions, patch }) => ({ filename, status, additions, deletions, patch }),
    );

    let diff: string | null = null;
    try {
        const { data: raw } = await github.rest.pulls.get({ ...params, mediaType: { format: 'diff' } });
        diff = String(raw);
    } catch (error) {
        if ((error as { status?: number }).status !== 406) {
            throw error;
        }
        core.warning(`The diff of #${number} is too large for the API; the agent gets the per-file patches instead`);
    }

    if (diff === null) {
        writeFileSync(`${dir}/pr.json`, JSON.stringify({ pr, files }, null, 2));
        return;
    }

    const filesWithoutPatch = files.map(({ patch: _patch, ...file }) => file);
    writeFileSync(`${dir}/pr.json`, JSON.stringify({ pr, files: filesWithoutPatch }, null, 2));
    writeFileSync(`${dir}/pr.diff`, diff);
}

export function notifyItems(agentOutput: { items?: AgentOutputItem[] }): AgentOutputItem[] {
    return (agentOutput.items ?? []).filter((item) => item.type === 'notify_docs_teams');
}

export async function postComment(
    { github, context, core }: { github: GitHub; context: Context; core: Core },
    agentOutput: { items?: AgentOutputItem[] },
): Promise<void> {
    const items = notifyItems(agentOutput);
    if (items.length === 0) {
        core.info('The agent reported no documentation impact');
        return;
    }
    if (items.length > 1) {
        throw new Error(`Expected at most one notify_docs_teams item, got ${items.length}`);
    }

    const [item] = items;
    if (!item.summary?.trim()) {
        throw new Error('notify_docs_teams item has an empty summary');
    }

    const number = await resolvePullRequestNumber({ github, context });
    if (number === null) {
        throw new Error(`The agent asked to notify, but no merged pull request is associated with ${context.sha}`);
    }

    const { data: pr } = await github.rest.pulls.get({ ...context.repo, pull_number: number });
    const labels = (pr as { labels: Array<{ name: string }> }).labels.map((label) => label.name);
    const body = buildComment(parseAudiences(item.audiences ?? ''), item.summary, labels);
    if (isDryRun(context)) {
        core.info(`Dry run: not commenting on #${number}`);
        await core.summary?.addRaw(`## Dry run: comment for #${number}\n\n${body}`, true).write();
        return;
    }

    const comments = await github.paginate<{ id: number; body?: string }>(github.rest.issues.listComments, {
        ...context.repo,
        issue_number: number,
        per_page: 100,
    });
    const existing = comments.find((comment) => comment.body?.startsWith(COMMENT_MARKER));

    if (existing) {
        await github.rest.issues.updateComment({ ...context.repo, comment_id: existing.id, body });
        core.info(`Updated documentation-impact comment ${existing.id} on #${number}`);
        return;
    }

    await github.rest.issues.createComment({ ...context.repo, issue_number: number, body });
    core.info(`Posted documentation-impact comment on #${number}`);
}
