/**
 * Daily report of the pull requests that changed what an unreleased major does, so each
 * one can be challenged before the gate for that major closes.
 *
 * It lists every pull request that carries `major/<version>` and was either merged into trunk
 * within the window or received the label within the window while still open. The label
 * itself is set by major-label.ts; this only reads it. A labelled pull request without an
 * `UPGRADE-<version>.md` change is listed first: either the upgrade guide is missing an entry
 * or the change needs none, and someone has to say which.
 */

import { FEATURE_REGISTRY_PATH, parseFeatureRegistry, resolveInFlightMajors } from './major-label.ts';

export type ReportEntry = {
    number: number;
    title: string;
    url: string;
    author: string;
    state: 'merged' | 'open';
    touchesUpgradeGuide: boolean;
};

type SearchItem = {
    number: number;
    title: string;
    html_url: string;
    user: { login: string } | null;
};

type LabelEvent = {
    event: string;
    created_at: string;
    label?: { name: string };
};

type RepoOptions = { owner: string; repo: string };

type GitHubRestClient = {
    paginate<T>(route: unknown, options: Record<string, unknown>): Promise<T[]>;
    rest: {
        search: { issuesAndPullRequests: unknown };
        issues: { listEvents: unknown };
        pulls: { listFiles: unknown };
    };
};

type Summary = {
    addRaw(text: string, addEOL?: boolean): Summary;
    write(): Promise<unknown>;
};

type CoreLogger = {
    info(message: string): void;
    summary: Summary;
};

export function windowStart(now: Date, days: number): Date {
    if (!Number.isFinite(days) || days <= 0) {
        throw new Error(`the report window must be a positive number of days, got ${days}`);
    }

    return new Date(now.getTime() - days * 24 * 60 * 60 * 1000);
}

export function searchTimestamp(date: Date): string {
    return date.toISOString().replace(/\.\d{3}Z$/, 'Z');
}

export function wasLabelledSince(events: LabelEvent[], label: string, since: Date): boolean {
    return events.some(
        (event) => event.event === 'labeled' && event.label?.name === label && new Date(event.created_at) >= since,
    );
}

function escapeTableCell(value: string): string {
    return value.replace(/[\\|]/g, '\\$&').replace(/\r?\n/g, ' ');
}

export function renderReport(options: { major: string; since: Date; entries: ReportEntry[] }): string {
    const { major, since, entries } = options;
    const heading = `## \`major/${major}\` since ${searchTimestamp(since)}`;

    if (entries.length === 0) {
        return `${heading}\n\nNo pull request was merged with or newly given this label.\n`;
    }

    const sorted = [...entries].sort(
        (a, b) => Number(a.touchesUpgradeGuide) - Number(b.touchesUpgradeGuide) || a.number - b.number,
    );
    const withoutGuide = entries.filter((entry) => !entry.touchesUpgradeGuide).length;
    const rows = sorted.map(
        (entry) =>
            `| [#${entry.number}](${entry.url}) | ${escapeTableCell(entry.title)} | @${entry.author} | ${entry.state} | ${
                entry.touchesUpgradeGuide ? 'yes' : '**no**'
            } |`,
    );

    return [
        heading,
        '',
        `${entries.length} pull request(s), ${withoutGuide} without a change to \`UPGRADE-${major}.md\`.`,
        '',
        `| Pull request | Title | Author | State | \`UPGRADE-${major}.md\` |`,
        '|---|---|---|---|---|',
        ...rows,
        '',
    ].join('\n');
}

async function search(github: GitHubRestClient, q: string): Promise<SearchItem[]> {
    return github.paginate<SearchItem>(github.rest.search.issuesAndPullRequests, { q, per_page: 100 });
}

async function touchesFile(github: GitHubRestClient, repo: RepoOptions, number: number, path: string): Promise<boolean> {
    const files = await github.paginate<{ filename: string }>(github.rest.pulls.listFiles, {
        ...repo,
        pull_number: number,
        per_page: 100,
    });

    return files.some(({ filename }) => filename === path);
}

export async function collectEntries(
    github: GitHubRestClient,
    repo: RepoOptions,
    major: string,
    since: Date,
): Promise<ReportEntry[]> {
    const label = `major/${major}`;
    const scope = `repo:${repo.owner}/${repo.repo} is:pr label:"${label}"`;
    const timestamp = searchTimestamp(since);

    const merged = (await search(github, `${scope} is:merged base:trunk merged:>=${timestamp}`)).map((item) => ({
        item,
        state: 'merged' as const,
    }));

    // `updated:` only narrows the candidates; the label event decides.
    const open: Array<{ item: SearchItem; state: 'open' }> = [];
    for (const item of await search(github, `${scope} is:open updated:>=${timestamp}`)) {
        const events = await github.paginate<LabelEvent>(github.rest.issues.listEvents, {
            ...repo,
            issue_number: item.number,
            per_page: 100,
        });
        if (wasLabelledSince(events, label, since)) {
            open.push({ item, state: 'open' });
        }
    }

    const entries: ReportEntry[] = [];
    for (const { item, state } of [...merged, ...open]) {
        entries.push({
            number: item.number,
            title: item.title,
            url: item.html_url,
            author: item.user?.login ?? 'ghost',
            state,
            touchesUpgradeGuide: await touchesFile(github, repo, item.number, `UPGRADE-${major}.md`),
        });
    }

    return entries;
}

export async function reportMajorLabels(
    { github, core, context }: { github: GitHubRestClient; core: CoreLogger; context: { repo: RepoOptions } },
    readFile: (path: string) => string,
    options: { now: Date; days: number },
): Promise<number> {
    const since = windowStart(options.now, options.days);
    const majors = resolveInFlightMajors(parseFeatureRegistry(readFile(FEATURE_REGISTRY_PATH)));

    if (majors.length === 0) {
        await core.summary.addRaw('## Major label report\n\nNo major is in flight in the feature registry.\n').write();

        return 1;
    }

    for (const major of majors) {
        const entries = await collectEntries(github, context.repo, major, since);
        core.info(`major/${major}: ${entries.length} pull request(s)`);
        core.summary.addRaw(renderReport({ major, since, entries }), true);
    }

    await core.summary.write();

    return majors.length;
}
