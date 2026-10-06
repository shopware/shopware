/**
 * Label a pull request for the major releases it affects.
 *
 * Two labels per major, because the markers mean two different things. `major/<version>`
 * says the pull request changes what that major does: it documents an upgrade step,
 * stabilises an experimental API, narrows a signature, or moves a major feature flag.
 * `major/<version>-cleanup` says it only leaves something behind for that major — an
 * `@deprecated tag:` annotation, or a new `@experimental stableVersion:` that someone has
 * to stabilise later. A pull request can earn both, and can earn labels for more than one
 * major at once.
 *
 * A marker counts only where the pull request changes how often it occurs in a file, so a
 * line that merely passes the flag to a reformatted call is not a change to the flag.
 * `@experimental` inside an `@internal` docblock counts for nothing: there is no public
 * API to stabilise.
 *
 * Pull requests into a release branch are not labelled: a backport or merge-back carries
 * changes whose trunk pull request already answers the question. Any other base counts,
 * because a stacked pull request is retargeted to trunk with an `edited` event that never
 * triggers this workflow.
 *
 * Most work for the next major ships inside a `6.7.x` minor behind a flag that defaults to
 * off, so the milestone label — which records the version that ships a change — cannot
 * carry this. These labels are the orthogonal axis and must never be milestone labels.
 *
 * In-flight majors are derived exactly as the test lanes derive theirs: a `major: true`
 * flag named after its version and still `default: false`. This is the TypeScript twin of
 * `shopware_in_flight_majors()` in `.github/bin/lib/feature-flags.php`; the two must agree,
 * or a major would get a lane without a label or the reverse. Registering the next major
 * flag adds its labels with nothing to maintain here.
 *
 * Signals that name no version — the unversioned major flags, an edit to the registry, a
 * path in `major-paths.yml` — belong to the nearest major, because that is the one that
 * flips them.
 *
 * Known gap: a pull request that changes flagged behaviour inside a shared file without
 * touching the flag line carries no marker and no path. `major-paths.yml` closes this for
 * features that own a namespace; nothing closes it for the rest.
 */

import { EXCLUDED_PATH_PREFIX, FEATURE_REGISTRY_PATH, splitDiffByFile } from './auto-label-major-tests.ts';

export { FEATURE_REGISTRY_PATH };

export const MAJOR_PATHS_PATH = '.github/major-paths.yml';

export type FeatureFlag = {
    name: string;
    major: boolean;
    default: boolean;
};

export type MajorLabels = {
    behaviour: boolean;
    cleanup: boolean;
};

type PullRequestDetectionContext = {
    eventName: string;
    payload: {
        action: string;
        pull_request: {
            base: { ref: string };
        };
    };
};

type PullRequestContext = PullRequestDetectionContext & {
    repo: {
        owner: string;
        repo: string;
    };
    payload: {
        action: string;
        pull_request: {
            number: number;
            base: { ref: string };
            labels?: Array<{ name: string }>;
        };
    };
};

export type PullRequestFile = {
    filename: string;
    patch?: string;
};

export type IssueEvent = {
    event: string;
    label?: { name: string };
};

type ListFilesOptions = {
    owner: string;
    repo: string;
    pull_number: number;
    per_page: number;
};

type ListEventsOptions = {
    owner: string;
    repo: string;
    issue_number: number;
    per_page: number;
};

type GitHubRestClient = {
    paginate: {
        (
            route: (options: ListFilesOptions) => Promise<{ data: PullRequestFile[] }>,
            options: ListFilesOptions,
        ): Promise<PullRequestFile[]>;
        (
            route: (options: ListEventsOptions) => Promise<{ data: IssueEvent[] }>,
            options: ListEventsOptions,
        ): Promise<IssueEvent[]>;
    };
    rest: {
        pulls: {
            get(options: {
                owner: string;
                repo: string;
                pull_number: number;
                mediaType: { format: 'diff' };
            }): Promise<{ data: unknown }>;
            listFiles(options: ListFilesOptions): Promise<{ data: PullRequestFile[] }>;
        };
        issues: {
            listEvents(options: ListEventsOptions): Promise<{ data: IssueEvent[] }>;
            addLabels(options: {
                owner: string;
                repo: string;
                issue_number: number;
                labels: string[];
            }): Promise<unknown>;
        };
    };
};

type CoreLogger = {
    info(message: string): void;
    warning(message: string): void;
};

export function parseFeatureRegistry(registryYaml: string): FeatureFlag[] {
    const flags: FeatureFlag[] = [];

    for (const line of registryYaml.split('\n')) {
        const name = line.match(/^\s*-\s*name:\s*(\S+)/);
        if (name) {
            flags.push({ name: name[1], major: false, default: false });
            continue;
        }

        const current = flags.at(-1);
        if (!current) {
            continue;
        }

        const major = line.match(/^\s*major:\s*(true|false)\b/);
        if (major) {
            current.major = major[1] === 'true';
            continue;
        }

        const defaultValue = line.match(/^\s*default:\s*(true|false)\b/);
        if (defaultValue) {
            current.default = defaultValue[1] === 'true';
        }
    }

    return flags;
}

/** A flag already defaulting to true has flipped and is not pending. */
export function pendingMajorFlags(flags: FeatureFlag[]): string[] {
    return flags.filter((flag) => flag.major && !flag.default).map((flag) => flag.name);
}

/**
 * The majors that have not shipped, oldest first — `['6.8', '6.9']`.
 * Mirrors `shopware_in_flight_majors()`, including its "named after its version" rule.
 */
export function resolveInFlightMajors(flags: FeatureFlag[]): string[] {
    return pendingMajorFlags(flags)
        .map((name) => name.match(/^v(\d+)\.(\d+)\.\d+\.\d+$/i))
        .filter((match): match is RegExpMatchArray => match !== null)
        .map((match) => [Number(match[1]), Number(match[2])] as const)
        .sort(([aMajor, aMinor], [bMajor, bMinor]) => aMajor - bMajor || aMinor - bMinor)
        .map(([major, minor]) => `${major}.${minor}`);
}

export function parseMajorPaths(mapYaml: string): string[] {
    const globs: string[] = [];

    for (const line of mapYaml.split('\n')) {
        if (/^\s*#/.test(line)) {
            continue;
        }

        const entry = line.match(/^\s+-\s*["']?([^"'\s]+)["']?\s*$/);
        if (entry) {
            globs.push(entry[1]);
        }
    }

    return globs;
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function globToRegExp(glob: string): RegExp {
    let pattern = '';

    for (let index = 0; index < glob.length; index++) {
        if (glob.startsWith('**/', index)) {
            pattern += '(?:.*/)?';
            index += 2;
        } else if (glob.startsWith('**', index)) {
            pattern += '.*';
            index += 1;
        } else if (glob[index] === '*') {
            pattern += '[^/]*';
        } else {
            pattern += escapeRegExp(glob[index]);
        }
    }

    return new RegExp(`^${pattern}$`);
}

/**
 * `behaviour` and `cleanup` count either way; `stabilisation` is the `stableVersion:`
 * annotation, which only changes the major when it is removed.
 */
type MarkerKind = 'behaviour' | 'cleanup' | 'stabilisation';

type LineMarker = {
    kind: MarkerKind;
    pattern: RegExp;
};

function isChangedLine(line: string): boolean {
    return (line.startsWith('+') || line.startsWith('-')) && !line.startsWith('+++') && !line.startsWith('---');
}

/**
 * Whether the changed line at `index` sits in a docblock that also says `@internal`. The
 * docblock is read as the line's own side of the diff sees it, within the line's hunk.
 */
function isInInternalDocblock(lines: string[], index: number): boolean {
    const side = lines[index][0];
    let hunkStart = index;
    while (hunkStart > 0 && !lines[hunkStart - 1].startsWith('@@')) {
        hunkStart--;
    }

    const view: string[] = [];
    let position = -1;
    for (let cursor = hunkStart; cursor < lines.length && !lines[cursor].startsWith('@@'); cursor++) {
        if (lines[cursor][0] === side || lines[cursor][0] === ' ') {
            if (cursor === index) {
                position = view.length;
            }
            view.push(lines[cursor]);
        }
    }

    let start = position;
    while (start >= 0 && !view[start].includes('/**')) {
        if (start < position && view[start].includes('*/')) {
            return false;
        }
        start--;
    }
    if (start < 0) {
        return false;
    }

    let end = position;
    while (end < view.length - 1 && !view[end].includes('*/')) {
        end++;
    }

    return view.slice(start, end + 1).some((line) => line.includes('@internal'));
}

function markerBalance(section: string, markers: LineMarker[]): Map<string, { kind: MarkerKind; delta: number }> {
    const balance = new Map<string, { kind: MarkerKind; delta: number }>();
    const lines = section.split('\n');

    lines.forEach((line, index) => {
        if (!isChangedLine(line)) {
            return;
        }

        markers.forEach(({ kind, pattern }, markerIndex) => {
            for (const match of line.matchAll(pattern)) {
                if (kind === 'stabilisation' && isInInternalDocblock(lines, index)) {
                    continue;
                }

                const key = `${markerIndex}:${match[0]}`;
                const entry = balance.get(key) ?? { kind, delta: 0 };
                entry.delta += line.startsWith('+') ? 1 : -1;
                balance.set(key, entry);
            }
        });
    });

    return balance;
}

export function evaluateMajorLabels(options: {
    diff: string;
    flags: FeatureFlag[];
    targetMajor: string;
    majorPaths: string[];
    /** The nearest in-flight major owns every signal that names no version. */
    isNextMajor: boolean;
}): MajorLabels {
    const { diff, flags, targetMajor, majorPaths, isNextMajor } = options;
    const files = splitDiffByFile(diff).filter(({ path }) => path && !path.startsWith(EXCLUDED_PATH_PREFIX));

    if (files.length === 0) {
        return { behaviour: false, cleanup: false };
    }

    const version = escapeRegExp(targetMajor);
    const versionFlag = escapeRegExp(`v${targetMajor}.0.0`);
    const unversionedFlags = isNextMajor
        ? pendingMajorFlags(flags)
              .filter((flag) => !/^v\d+\.\d+\.\d+\.\d+$/i.test(flag))
              .map(escapeRegExp)
        : [];
    const flagAlternatives = [versionFlag, ...unversionedFlags].join('|');
    const pathMatchers = isNextMajor ? majorPaths.map(globToRegExp) : [];

    // `(?!\d)` keeps v6.8.0 from matching a v6.8.01 that a future scheme might introduce
    const markers: LineMarker[] = [
        { kind: 'stabilisation', pattern: new RegExp(`stableVersion:v${version}\\.0(?!\\d)`, 'g') },
        { kind: 'behaviour', pattern: new RegExp(`version:\\s*'v${version}\\.0'`, 'g') },
        // A flag name only counts where a consuming construct delimits it. Backtick-wrapped
        // prose is deliberately excluded: release notes describe flags without changing them.
        { kind: 'behaviour', pattern: new RegExp(`['"](${flagAlternatives})['"]`, 'g') },
        { kind: 'behaviour', pattern: new RegExp(`<flag>(${flagAlternatives})</flag>`, 'g') },
        { kind: 'behaviour', pattern: new RegExp(`\\b(${flagAlternatives})\\s*:`, 'g') },
        { kind: 'cleanup', pattern: new RegExp(`tag:v${version}\\.0(?!\\d)`, 'g') },
    ];

    const behaviourPath = files.some(
        ({ path }) =>
            path === `UPGRADE-${targetMajor}.md` ||
            (isNextMajor && path === FEATURE_REGISTRY_PATH) ||
            pathMatchers.some((matcher) => matcher.test(path)),
    );

    const labels: MajorLabels = { behaviour: behaviourPath, cleanup: false };

    for (const { section } of files) {
        for (const { kind, delta } of markerBalance(section, markers).values()) {
            if (delta === 0) {
                continue;
            }

            if (kind === 'behaviour' || (kind === 'stabilisation' && delta < 0)) {
                labels.behaviour = true;
            } else {
                labels.cleanup = true;
            }
        }
    }

    return labels;
}

export function labelNamesFor(targetMajor: string, labels: MajorLabels): string[] {
    const names: string[] = [];

    if (labels.behaviour) {
        names.push(`major/${targetMajor}`);
    }

    if (labels.cleanup) {
        names.push(`major/${targetMajor}-cleanup`);
    }

    return names;
}

export function labelsForDiff(options: {
    diff: string;
    flags: FeatureFlag[];
    majorPaths: string[];
}): string[] {
    const { diff, flags, majorPaths } = options;

    return resolveInFlightMajors(flags).flatMap((targetMajor, index) =>
        labelNamesFor(
            targetMajor,
            evaluateMajorLabels({ diff, flags, targetMajor, majorPaths, isNextMajor: index === 0 }),
        ),
    );
}

/** `6.6.x`, `6.7.14.x`, and the exact-version branches a security release is prepared on. */
export function isReleaseBranch(ref: string): boolean {
    return /^\d+(\.\d+)+(\.x)?$/.test(ref);
}

// All run conditions live here so they are unit-testable instead of an untestable YAML expression.
export function shouldDetect(context: PullRequestDetectionContext): boolean {
    if (context.eventName !== 'pull_request_target') {
        return false;
    }

    if (isReleaseBranch(context.payload.pull_request.base.ref)) {
        return false;
    }

    return ['opened', 'reopened', 'synchronize', 'ready_for_review'].includes(context.payload.action);
}

export function missingLabels(context: PullRequestContext, wanted: string[]): string[] {
    const present = new Set((context.payload.pull_request.labels ?? []).map((label) => label.name));

    return wanted.filter((label) => !present.has(label));
}

/** Labels are only ever added: whoever removed one has overruled the detection for good. */
export function withoutRemovedLabels(labels: string[], events: IssueEvent[]): string[] {
    const removed = new Set(
        events.filter(({ event }) => event === 'unlabeled').map(({ label }) => label?.name),
    );

    return labels.filter((label) => !removed.has(label));
}

/** GitHub answers 406 `too_large` once a diff passes 300 files or 20,000 lines. */
export function isDiffTooLarge(error: unknown): boolean {
    const requestError = error as {
        status?: number;
        response?: { data?: { errors?: Array<{ field?: string; code?: string }> } };
    } | undefined;

    return (
        requestError?.status === 406 &&
        (requestError.response?.data?.errors ?? []).some((entry) => entry.field === 'diff' && entry.code === 'too_large')
    );
}

/**
 * Rebuilds a unified diff from the files endpoint, in the shape `splitDiffByFile` reads.
 * GitHub omits the patch of a single oversized file; that file still counts for path matches.
 */
export function diffFromFiles(files: PullRequestFile[]): string {
    return files
        .map(
            ({ filename, patch }) =>
                `diff --git a/${filename} b/${filename}\n--- a/${filename}\n+++ b/${filename}\n${patch ?? ''}`,
        )
        .join('\n');
}

async function fetchDiff({
    github,
    core,
    context,
}: {
    github: GitHubRestClient;
    core: CoreLogger;
    context: PullRequestContext;
}): Promise<string> {
    const pullRequest = {
        owner: context.repo.owner,
        repo: context.repo.repo,
        pull_number: context.payload.pull_request.number,
    };

    try {
        const { data } = await github.rest.pulls.get({ ...pullRequest, mediaType: { format: 'diff' } });

        return String(data);
    } catch (error) {
        if (!isDiffTooLarge(error)) {
            throw error;
        }
    }

    // The files endpoint stops at 3,000 files; beyond that some markers go unseen.
    const files = await github.paginate(github.rest.pulls.listFiles, { ...pullRequest, per_page: 100 });
    const withoutPatch = files.filter(({ patch }) => patch === undefined).length;
    core.warning(
        `diff too large, read ${files.length} file(s) through the files endpoint instead` +
            (withoutPatch > 0 ? `; ${withoutPatch} without a patch are matched by path only` : ''),
    );

    return diffFromFiles(files);
}

export async function detectMajorLabels(
    { github, core, context }: { github: GitHubRestClient; core: CoreLogger; context: PullRequestContext },
    readFile: (path: string) => string,
): Promise<string[]> {
    if (!shouldDetect(context)) {
        core.info(
            `skipping major-label detection: ${context.eventName}/${context.payload.action} into ${context.payload.pull_request.base.ref} is not a trigger`,
        );

        return [];
    }

    // Base ref, never the PR head: a fork must not be able to edit the registry or the path
    // map that judge it. A pull request that adds a flag is covered by the registry path hit.
    const flags = parseFeatureRegistry(readFile(FEATURE_REGISTRY_PATH));
    const majors = resolveInFlightMajors(flags);

    if (majors.length === 0) {
        core.info('no in-flight major in the registry, nothing to label');

        return [];
    }

    const diff = await fetchDiff({ github, core, context });
    const majorPaths = parseMajorPaths(readFile(MAJOR_PATHS_PATH));
    const wanted = labelsForDiff({ diff, flags, majorPaths });
    const missing = missingLabels(context, wanted);

    core.info(
        wanted.length === 0
            ? `no markers for ${majors.join(', ')} in the diff`
            : `markers found: ${wanted.join(', ')}${missing.length === 0 ? ' (already applied)' : ''}`,
    );

    if (missing.length === 0) {
        return [];
    }

    // The payload only shows the labels as they are now, not which ones were taken off.
    const events = await github.paginate(github.rest.issues.listEvents, {
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: context.payload.pull_request.number,
        per_page: 100,
    });
    const allowed = withoutRemovedLabels(missing, events);
    const overruled = missing.filter((label) => !allowed.includes(label));

    if (overruled.length > 0) {
        core.info(`not re-adding ${overruled.join(', ')}: removed from this pull request before`);
    }

    return allowed;
}

export async function applyMajorLabels(
    { github, context }: { github: GitHubRestClient; context: PullRequestContext },
    labels: string[],
): Promise<void> {
    if (labels.length === 0) {
        return;
    }

    await github.rest.issues.addLabels({
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: context.payload.pull_request.number,
        labels,
    });
}
