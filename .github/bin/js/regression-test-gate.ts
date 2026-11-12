/**
 * Requires a regression test from a pull request that fixes an Administration bug.
 *
 * A pull request that closes an issue of type Bug and changes Administration source code must add at least one
 * Jest doc comment tagged `@regression` whose `@see` links one of the closed bugs. The tags are described in
 * src/Administration/Resources/app/administration/technical-docs/11-code-quality/03-test-annotations.md.
 * The `no-regression-test` label skips the check; the job summary then names it, so the exception stays visible.
 */

export const ADMIN_ROOT = 'src/Administration/Resources/app/administration/src/';
export const SKIP_LABEL = 'no-regression-test';

const SPEC = /\.spec(\/.+)?\.(js|ts)$/;
const SOURCE = /\.(js|ts|vue|twig)$/;

export type ClosingIssue = {
    url: string;
    issueType: { name: string } | null;
    labels: { nodes: { name: string }[] };
};

export type Verdict = { state: 'success' | 'failure' | 'skipped'; message: string };

/** A Jest spec of the Administration, including the files of a split `.spec/` directory. */
export function isAdminSpec(path: string): boolean {
    return path.startsWith(ADMIN_ROOT) && SPEC.test(path);
}

/** Administration code that a Jest test could cover. */
export function isAdminSource(path: string): boolean {
    return path.startsWith(ADMIN_ROOT) && SOURCE.test(path) && !SPEC.test(path);
}

/** The URLs of the closing issues that are bugs, by issue type or by the `bug` label. */
export function bugUrls(issues: ClosingIssue[]): string[] {
    return issues
        .filter((issue) => issue.issueType?.name === 'Bug' || issue.labels.nodes.some((label) => label.name.toLowerCase() === 'bug'))
        .map((issue) => issue.url);
}

/**
 * Counts the doc comments tagged `@regression` whose `@see` links one of the bugs, with or without an anchor.
 *
 * @example
 * countRegressionComments('/**\n * @regression\n * @see https://github.com/shopware/shopware/issues/1\n *\/', ['https://github.com/shopware/shopware/issues/1']); // 1
 */
export function countRegressionComments(source: string, bugs: string[]): number {
    const comments = source.match(/\/\*\*[\s\S]*?\*\//g) ?? [];

    return comments.filter((comment) => {
        if (!/@regression(?![\w-])/.test(comment)) {
            return false;
        }

        const sources = [...comment.matchAll(/@see\s+(\S+)/g)].map((match) => match[1].split('#')[0]);

        return sources.some((source) => bugs.includes(source));
    }).length;
}

/**
 * Decides the gate. Pull requests that close no bug or change no Administration code are skipped.
 *
 * @example
 * evaluate({ bugs: [url], labels: [], files: [source], added: 0 }).state; // 'failure'
 */
export function evaluate({
    bugs,
    labels,
    files,
    added,
}: {
    bugs: string[];
    labels: string[];
    files: string[];
    added: number;
}): Verdict {
    if (bugs.length === 0) {
        return { state: 'skipped', message: 'The pull request closes no bug.' };
    }

    if (!files.some(isAdminSource)) {
        return { state: 'skipped', message: 'The pull request changes no Administration source code.' };
    }

    if (labels.includes(SKIP_LABEL)) {
        return { state: 'skipped', message: `Skipped by the \`${SKIP_LABEL}\` label, without a regression test for ${bugs.join(', ')}.` };
    }

    if (added > 0) {
        return { state: 'success', message: `Adds ${added} regression test comment(s) for ${bugs.join(', ')}.` };
    }

    return {
        state: 'failure',
        message: `The pull request fixes ${bugs.join(', ')} but adds no Jest test tagged \`@regression\` with \`@see\` to it. Add one, or ask a maintainer for the \`${SKIP_LABEL}\` label when the bug cannot be covered by Jest.`,
    };
}

type Logger = {
    info(message: string): void;
    setFailed(message: string): void;
    summary: { addRaw(text: string): { write(): Promise<unknown> } };
};

type GateClient = {
    graphql<T>(query: string, variables: Record<string, unknown>): Promise<T>;
    paginate<T>(method: unknown, params: object): Promise<T[]>;
    rest: {
        pulls: { listFiles: unknown };
        repos: {
            getContent(options: { owner: string; repo: string; path: string; ref: string; mediaType: { format: 'raw' } }): Promise<{ data: unknown }>;
        };
    };
};

type GateContext = {
    repo: { owner: string; repo: string };
    payload: {
        pull_request?: { number: number; base: { sha: string }; head: { sha: string }; labels: { name: string }[] };
    };
};

const CLOSING_ISSUES_QUERY = `query($owner: String!, $repo: String!, $number: Int!) {
  repository(owner: $owner, name: $repo) {
    pullRequest(number: $number) {
      closingIssuesReferences(first: 25) {
        nodes { url issueType { name } labels(first: 20) { nodes { name } } }
      }
    }
  }
}`;

/** A file at a commit, or an empty string when the commit does not have it. */
async function contentAt(github: GateClient, repo: GateContext['repo'], path: string, ref: string): Promise<string> {
    try {
        const { data } = await github.rest.repos.getContent({ ...repo, path, ref, mediaType: { format: 'raw' } });

        return String(data);
    } catch (error) {
        if ((error as { status?: number }).status === 404) {
            return '';
        }

        throw error;
    }
}

/** Entry point for actions/github-script on pull_request events. */
export async function checkRegressionTest({ github, core, context }: { github: GateClient; core: Logger; context: GateContext }): Promise<void> {
    const pullRequest = context.payload.pull_request;
    if (!pullRequest) {
        throw new Error('The regression test gate only runs on pull_request events.');
    }

    const { repository } = await github.graphql<{
        repository: { pullRequest: { closingIssuesReferences: { nodes: ClosingIssue[] } } };
    }>(CLOSING_ISSUES_QUERY, { ...context.repo, number: pullRequest.number });
    const bugs = bugUrls(repository.pullRequest.closingIssuesReferences.nodes);
    const changes = await github.paginate<{ filename: string; previous_filename?: string }>(github.rest.pulls.listFiles, {
        ...context.repo,
        pull_number: pullRequest.number,
        per_page: 100,
    });
    const files = changes.map((file) => file.filename);

    let added = 0;
    if (bugs.length > 0) {
        for (const change of changes.filter((file) => isAdminSpec(file.filename))) {
            const basePath = change.previous_filename ?? change.filename;
            const before = countRegressionComments(await contentAt(github, context.repo, basePath, pullRequest.base.sha), bugs);
            const after = countRegressionComments(await contentAt(github, context.repo, change.filename, pullRequest.head.sha), bugs);
            added += Math.max(0, after - before);
        }
    }

    const verdict = evaluate({ bugs, labels: pullRequest.labels.map((label) => label.name), files, added });
    await core.summary.addRaw(`## Regression test\n\n${verdict.message}\n`).write();

    if (verdict.state === 'failure') {
        core.setFailed(verdict.message);

        return;
    }

    core.info(verdict.message);
}
