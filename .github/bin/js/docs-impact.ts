import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';

export const AUDIENCES = ['end-user', 'developer'] as const;

export type Audience = (typeof AUDIENCES)[number];

export const COMMENT_MARKER = '<!-- sw-docs-impact -->';

export const DOCS_CANDIDATE_LABELS: Record<Audience, string> = {
    'end-user': 'docs-candidate/end-user',
    developer: 'docs-candidate/developer',
};

const MAX_SUMMARY_LENGTH = 20000;

const AUDIENCE_HEADINGS: Record<string, string> = {
    'End-user docs': '🛍️ End-user docs',
    'Developer docs': '🛠️ Developer docs',
};

export function decorateHeadings(summary: string): string {
    return summary.replace(/^\*\*(End-user docs|Developer docs)\*\*[ \t]*$/gm, (_heading, name: string) => `**${AUDIENCE_HEADINGS[name]}**`);
}

const SKIPPED_COMMIT_TYPES = /^(test|ci|chore|docs|refactor)(\([^)]*\))?:/;

export const MAX_DISPATCHED_PULL_REQUESTS = 20;

export const DOCS_IMPACT_WORKFLOW = 'sw-docs-impact.lock.yml';

const BOT_LOGIN = 'github-actions[bot]';

export function shouldCheck(commitMessage: string): boolean {
    return !SKIPPED_COMMIT_TYPES.test(commitMessage.trimStart());
}

type Repo = { owner: string; repo: string };

type GitHub = {
    rest: {
        repos: {
            compareCommitsWithBasehead(params: Repo & { basehead: string; per_page: number; page: number }): Promise<{
                data: { commits: Array<{ sha: string; commit: { message: string } }> };
            }>;
            listPullRequestsAssociatedWithCommit(params: Repo & { commit_sha: string }): Promise<{
                data: Array<{ number: number; merged_at: string | null }>;
            }>;
        };
        actions: {
            createWorkflowDispatch(params: Repo & { workflow_id: string; ref: string; inputs: Record<string, string> }): Promise<unknown>;
        };
        pulls: {
            get(params: Repo & { pull_number: number; mediaType?: { format: string } }): Promise<{ data: unknown }>;
            listFiles(params: Repo & { pull_number: number; per_page: number }): Promise<{ data: unknown }>;
        };
        issues: {
            listComments(params: Repo & { issue_number: number; per_page: number }): Promise<{
                data: Array<{ id: number; body?: string; user?: { login: string } | null }>;
            }>;
            createComment(params: Repo & { issue_number: number; body: string }): Promise<unknown>;
            updateComment(params: Repo & { comment_id: number; body: string }): Promise<unknown>;
            addLabels(params: Repo & { issue_number: number; labels: string[] }): Promise<unknown>;
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
    payload: {
        inputs?: { pr_number?: string | number; dry_run?: string | boolean };
        before?: string;
        after?: string;
        head_commit?: { id: string; message: string } | null;
    };
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

export function buildComment(summary: string): string {
    let body = decorateHeadings(neutraliseMentions(summary.trim()));
    if (body.length > MAX_SUMMARY_LENGTH) {
        body = `${body.slice(0, MAX_SUMMARY_LENGTH)}\n\n_(truncated)_`;
    }

    return [
        COMMENT_MARKER,
        '### Docs candidate',
        '',
        '_⚠️ Automated note: this change may be worth mentioning in the documentation. No action required._',
        '',
        body,
    ].join('\n');
}

export function isDryRun(context: Context): boolean {
    const raw = context.payload.inputs?.dry_run;

    return raw === true || raw === 'true';
}

export function requestedPullRequestNumber(context: Context): number {
    const raw = context.payload.inputs?.pr_number;
    const number = Number(raw);
    if (raw === undefined || raw === '' || !Number.isInteger(number) || number <= 0) {
        throw new Error(`Invalid pr_number input: ${raw}`);
    }

    return number;
}

type Commit = { sha: string; message: string };

export async function pushedCommits({ github, context }: { github: GitHub; context: Context }): Promise<Commit[]> {
    const { before, after, head_commit: head } = context.payload;
    if (!before || !after || /^0+$/.test(before)) {
        return head ? [{ sha: head.id, message: head.message }] : [];
    }

    const commits: Commit[] = [];
    for (let page = 1; ; page++) {
        const { data } = await github.rest.repos.compareCommitsWithBasehead({ ...context.repo, basehead: `${before}...${after}`, per_page: 100, page });
        commits.push(...data.commits.map(({ sha, commit }) => ({ sha, message: commit.message })));
        if (data.commits.length < 100) {
            return commits;
        }
    }
}

export async function mergedPullRequests({ github, context, core }: { github: GitHub; context: Context; core: Core }): Promise<number[]> {
    const numbers = new Set<number>();
    for (const commit of await pushedCommits({ github, context })) {
        if (!shouldCheck(commit.message)) {
            core.info(`Skipping ${commit.sha}: ${commit.message.split('\n')[0]}`);
            continue;
        }

        const { data } = await github.rest.repos.listPullRequestsAssociatedWithCommit({ ...context.repo, commit_sha: commit.sha });
        for (const pr of data) {
            if (pr.merged_at !== null) {
                numbers.add(pr.number);
            }
        }
    }

    return [...numbers];
}

export async function dispatchDocsChecks(
    { github, context, core }: { github: GitHub; context: Context; core: Core },
    env: Record<string, string | undefined> = process.env,
): Promise<void> {
    const numbers = await mergedPullRequests({ github, context, core });
    if (numbers.length > MAX_DISPATCHED_PULL_REQUESTS) {
        core.warning(`Not checking ${numbers.slice(MAX_DISPATCHED_PULL_REQUESTS).map((n) => `#${n}`).join(', ')}: at most ${MAX_DISPATCHED_PULL_REQUESTS} pull requests per push`);
    }

    const dispatched = numbers.slice(0, MAX_DISPATCHED_PULL_REQUESTS);
    const dryRun = String(env.SW_DOCS_IMPACT_POST !== 'true');
    for (const number of dispatched) {
        await github.rest.actions.createWorkflowDispatch({
            ...context.repo,
            workflow_id: DOCS_IMPACT_WORKFLOW,
            ref: 'trunk',
            inputs: { pr_number: String(number), dry_run: dryRun },
        });
        core.info(`Dispatched the docs check for #${number} (dry_run: ${dryRun})`);
    }

    const list = dispatched.length > 0 ? dispatched.map((n) => `#${n}`).join(', ') : 'none';
    await core.summary?.addRaw(`Docs checks dispatched for ${context.sha}: ${list}`, true).write();
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
    const number = requestedPullRequestNumber(context);
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

export function candidateItems(agentOutput: { items?: AgentOutputItem[] }): AgentOutputItem[] {
    return (agentOutput.items ?? []).filter((item) => item.type === 'docs_candidate');
}

const MIN_SECRET_LENGTH = 8;

export function redactSecrets(text: string, raw: string | undefined): string {
    if (!raw) {
        return text;
    }

    const values = [raw, ...raw.split(',').flatMap((pair) => {
        const value = pair.slice(pair.indexOf('=') + 1).trim();
        return [value, value.replace(/^Bearer\s+/i, '')];
    })];

    return [...new Set(values)]
        .filter((value) => value.length >= MIN_SECRET_LENGTH)
        .sort((a, b) => b.length - a.length)
        .reduce((redacted, value) => redacted.split(value).join('[redacted]'), text);
}

const DOCS_LINK =/\[([^\]]+)\]\((https:\/\/(?:docs|developer)\.shopware\.com\/[^\s)]*)\)/g;

export type DocsPage = { dead: boolean; text: string | null };

export type PageLoader = (url: string) => Promise<DocsPage>;

const ENTITIES: Record<string, string> = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ' };

function decodeEntities(text: string): string {
    return text.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (entity, code: string) => {
        if (code[0] === '#') {
            return String.fromCodePoint(code[1].toLowerCase() === 'x' ? parseInt(code.slice(2), 16) : Number(code.slice(1)));
        }

        return ENTITIES[code.toLowerCase()] ?? entity;
    });
}

export function normaliseText(text: string): string {
    return decodeEntities(text)
        .replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/[*_`]/g, '')
        .replace(/\s+/g, ' ')
        .replace(/ ([,.;:!?)])/g, '$1')
        .trim();
}

const BLOCK_TAG = /^<\/?(?:p|div|li|ul|ol|h[1-6]|br|tr|td|th|table|section|article|header|footer|nav|blockquote|pre|dt|dd)\b/i;

export function pageText(html: string): string {
    let text = '';
    let index = 0;
    while (index < html.length) {
        const start = html.indexOf('<', index);
        const end = start === -1 ? -1 : html.indexOf('>', start);
        if (end === -1) {
            text += html.slice(index);
            break;
        }

        text += html.slice(index, start) + (BLOCK_TAG.test(html.slice(start, end)) ? ' ' : '');
        index = end + 1;
    }

    return normaliseText(text.replace(/[<>]/g, ' '));
}

export async function loadDocsPage(url: string): Promise<DocsPage> {
    try {
        const response = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(10000) });
        if (!response.ok) {
            await response.body?.cancel();

            return { dead: response.status === 404 || response.status === 410, text: null };
        }

        return { dead: false, text: pageText(await response.text()) };
    } catch {
        return { dead: false, text: null };
    }
}

export async function loadLinkedPages(summary: string, load: PageLoader): Promise<Map<string, DocsPage>> {
    const urls = [...new Set([...summary.matchAll(DOCS_LINK)].map((match) => match[2]))];

    return new Map(await Promise.all(urls.map(async (url) => [url, await load(url)] as const)));
}

export function unlinkDeadDocsLinks(summary: string, pages: Map<string, DocsPage>): string {
    return summary.replace(DOCS_LINK, (link, text: string, url: string) => (pages.get(url)?.dead ? text : link));
}

const EVIDENCE_LINE = /^[ \t]+(Docs|PR) quote:[ \t]*(.*?)[ \t]*$/;

const MAX_QUOTES_PER_KIND = 3;

const MAX_QUOTE_LENGTH = 300;

const MIN_QUOTE_LENGTH = 12;

function diffLines(diff: string): string[] {
    return diff.split('\n').filter((line) => /^[+\- ]/.test(line) && !/^(\+\+\+|---) /.test(line));
}

function renderEvidence(docs: string[], code: string[]): string[] {
    const lines = ['  <details><summary>Evidence</summary>', ''];
    if (docs.length > 0) {
        lines.push('  The docs say:', '', ...docs.map((quote) => `  > ${quote}`), '');
    }
    if (code.length > 0) {
        lines.push('  The pull request changes:', '', '  ```diff', ...code.map((line) => `  ${line}`), '  ```', '');
    }
    lines.push('  </details>');

    return lines;
}

export function attachEvidence(summary: string, pages: Map<string, DocsPage>, diff: string | null): string {
    const changed = diff === null ? [] : diffLines(diff).map((line) => ({ line, text: normaliseText(line.slice(1)) }));
    const output: string[] = [];
    let page: string | null = null;
    let docs: string[] = [];
    let code: string[] = [];

    const flush = () => {
        if (docs.length > 0 || code.length > 0) {
            output.push(...renderEvidence(docs, code));
        }
        docs = [];
        code = [];
    };

    for (const line of summary.split('\n')) {
        const evidence = line.match(EVIDENCE_LINE);
        if (!evidence) {
            flush();
            output.push(line);
            if (/^\s*- /.test(line)) {
                const url = [...line.matchAll(DOCS_LINK)][0]?.[2];
                page = url ? (pages.get(url)?.text ?? null) : null;
            } else if (line.trim() !== '') {
                page = null;
            }
            continue;
        }

        const [, kind, quote] = evidence;
        const wanted = normaliseText(kind === 'PR' ? quote.replace(/^[+-]/, '') : quote);
        if (wanted.length < MIN_QUOTE_LENGTH || quote.length > MAX_QUOTE_LENGTH || quote.includes('```')) {
            continue;
        }
        if (kind === 'Docs' && page?.includes(wanted) && docs.length < MAX_QUOTES_PER_KIND) {
            docs.push(quote);
        }
        const match = kind === 'PR' ? changed.find(({ text }) => text.includes(wanted)) : undefined;
        if (match && code.length < MAX_QUOTES_PER_KIND && !code.includes(match.line)) {
            code.push(match.line);
        }
    }
    flush();

    return output.join('\n');
}

async function pullRequestDiff({ github, context }: { github: GitHub; context: Context }, number: number): Promise<string> {
    const params = { ...context.repo, pull_number: number };
    try {
        const { data } = await github.rest.pulls.get({ ...params, mediaType: { format: 'diff' } });

        return String(data);
    } catch (error) {
        if ((error as { status?: number }).status !== 406) {
            throw error;
        }
    }

    const files = await github.paginate<PullRequestFile>(github.rest.pulls.listFiles, { ...params, per_page: 100 });

    return files.map((file) => file.patch ?? '').join('\n');
}

export async function postComment(
    { github, context, core }: { github: GitHub; context: Context; core: Core },
    agentOutput: { items?: AgentOutputItem[] },
    load: PageLoader = loadDocsPage,
): Promise<void> {
    const items = candidateItems(agentOutput);
    if (items.length === 0) {
        core.info('The agent reported no documentation impact');
        return;
    }
    if (items.length > 1) {
        throw new Error(`Expected at most one docs_candidate item, got ${items.length}`);
    }

    const [item] = items;
    if (!item.summary?.trim()) {
        throw new Error('docs_candidate item has an empty summary');
    }

    const number = requestedPullRequestNumber(context);
    const audiences = parseAudiences(item.audiences ?? '');
    const summary = redactSecrets(item.summary, process.env.SW_DOCS_IMPACT_REDACT);
    const pages = await loadLinkedPages(summary, load);
    const withEvidence = attachEvidence(summary, pages, await pullRequestDiff({ github, context }, number));
    const body = buildComment(unlinkDeadDocsLinks(withEvidence, pages));
    if (isDryRun(context)) {
        core.info(`Dry run: not commenting on #${number}`);
        await core.summary?.addRaw(`## Dry run: comment for #${number}\n\n${body}`, true).write();
        return;
    }

    const comments = await github.paginate<{ id: number; body?: string; user?: { login: string } | null }>(github.rest.issues.listComments, {
        ...context.repo,
        issue_number: number,
        per_page: 100,
    });
    const existing = comments.find((comment) => comment.user?.login === BOT_LOGIN && comment.body?.startsWith(COMMENT_MARKER));

    if (existing) {
        await github.rest.issues.updateComment({ ...context.repo, comment_id: existing.id, body });
        core.info(`Updated documentation-impact comment ${existing.id} on #${number}`);
    } else {
        await github.rest.issues.createComment({ ...context.repo, issue_number: number, body });
        core.info(`Posted documentation-impact comment on #${number}`);
    }

    await github.rest.issues.addLabels({ ...context.repo, issue_number: number, labels: audiences.map((audience) => DOCS_CANDIDATE_LABELS[audience]) });
}

export const END_USER_DOCS_SECTIONS: Record<string, string> = {
    'shopware-6/migration-en': 'https://docs.shopware.com/en/migration-en',
    'shopware-6': 'https://docs.shopware.com/en/shopware-6-en',
    'shopware-account': 'https://docs.shopware.com/en/account-en',
};

function endUserUrl(page: string): string {
    const prefix = Object.keys(END_USER_DOCS_SECTIONS).find((section) => page === section || page.startsWith(`${section}/`)) as string;

    return END_USER_DOCS_SECTIONS[prefix] + page.slice(prefix.length);
}

const MIN_END_USER_PAGES = 100;

const ARTICLE_VERSION = /^v(\d+(?:-\d+)*)\.md$/;

export type EndUserPage = { source: string; target: string; url: string };

function compareArticleVersions(a: string, b: string): number {
    const left = a.split('-').map(Number);
    const right = b.split('-').map(Number);
    for (let i = 0; i < Math.max(left.length, right.length); i++) {
        const diff = (left[i] ?? 0) - (right[i] ?? 0);
        if (diff !== 0) {
            return diff;
        }
    }

    return 0;
}

export function latestEndUserPages(files: string[]): EndUserPage[] {
    const latest = new Map<string, { file: string; version: string | null }>();

    for (const file of files) {
        const [section, ...rest] = file.split('/');
        const name = rest.pop();
        if (!name || !(section in END_USER_DOCS_SECTIONS)) {
            continue;
        }

        const version = name === 'index.md' ? null : name.match(ARTICLE_VERSION)?.[1];
        if (version === undefined) {
            continue;
        }

        const page = [section, ...rest].join('/');
        const current = latest.get(page);
        if (!current || (version !== null && (current.version === null || compareArticleVersions(version, current.version) > 0))) {
            latest.set(page, { file, version });
        }
    }

    return [...latest]
        .map(([page, { file }]) => ({ source: file, target: `${page}.md`, url: endUserUrl(page) }))
        .sort((a, b) => a.target.localeCompare(b.target));
}

export function buildEndUserDocs(contentDir: string, outDir: string): number {
    const files = (readdirSync(contentDir, { recursive: true }) as string[]).filter((file) => file.endsWith('.md'));
    const pages = latestEndUserPages(files);
    if (pages.length < MIN_END_USER_PAGES) {
        throw new Error(`Only ${pages.length} end-user pages found in ${contentDir}; expected at least ${MIN_END_USER_PAGES}`);
    }

    for (const page of pages) {
        const target = join(outDir, page.target);
        mkdirSync(dirname(target), { recursive: true });
        writeFileSync(target, `URL: ${page.url}\n\n${readFileSync(join(contentDir, page.source), 'utf8')}`);
    }

    return pages.length;
}

export async function warnIfEndUserDocsMissing(core: Core, dir: string): Promise<void> {
    if (existsSync(dir)) {
        return;
    }

    const message = 'End-user documentation is unavailable, so this run cannot link docs.shopware.com pages. Check the octo-sts token and the index steps above.';
    core.warning(message);
    await core.summary?.addRaw(`> [!WARNING]\n> ${message}`, true).write();
}
