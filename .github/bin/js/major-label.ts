/**
 * Label a pull request for the major releases it affects.
 *
 * Two labels per major, because the markers mean two different things. `major/<version>`
 * says the pull request changes what that major does: it documents an upgrade step,
 * stabilises an experimental API, narrows a signature, or moves a major feature flag.
 * `major/<version>-cleanup` says it only leaves something behind for that major — an
 * `@deprecated tag:` annotation or the call that reports it, or a new
 * `@experimental stableVersion:` that someone has to stabilise later. A pull request can
 * earn both, and can earn labels for more than one major at once.
 *
 * A marker counts only where the pull request changes how often it occurs in a file, so a
 * line that merely passes the flag to a reformatted call is not a change to the flag.
 * `@experimental` inside an `@internal` docblock counts for nothing: there is no public
 * API to stabilise.
 *
 * Pull requests into a release branch are not labelled: a backport or merge-back carries
 * changes whose trunk pull request already answers the question. Any other base counts,
 * including a stacked pull request, and changing the base re-runs the detection: the diff
 * against the new base is a different change.
 *
 * The labels follow the current diff. A label this workflow set goes again once no marker
 * supports it, unless the diff could not be read in full. A label a person set stays, and
 * one a person removed is never added back.
 *
 * Most work for the next major ships inside a `6.7.x` minor behind a flag that defaults to
 * off, so the milestone label — which records the version that ships a change — cannot
 * carry this. These labels are the orthogonal axis and must never be milestone labels.
 *
 * In-flight majors are version-shaped flags still set to `default: false`.
 * Registering the next major flag adds its labels with nothing
 * to maintain here; the test workflows opt into their target version explicitly.
 *
 * Signals that name no version — an edit to the registry, a
 * path in `major-paths.yml` — belong to the nearest major, because that is the one that
 * flips them.
 *
 * Known gap: a pull request that changes flagged behaviour inside a shared file without
 * touching the flag line carries no marker and no path. `major-paths.yml` closes this for
 * features that own a namespace; nothing closes it for the rest.
 */

import {
    EXCLUDED_PATH_PREFIX,
    FEATURE_REGISTRY_PATH,
    type PullRequestDiffClient,
    fetchPullRequestDiff,
    splitDiffByFile,
} from './auto-label-major-tests.ts';

export { FEATURE_REGISTRY_PATH };

export const MAJOR_PATHS_PATH = '.github/major-paths.yml';

export type FeatureFlag = {
    name: string;
    major?: string;
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
        changes?: { base?: unknown };
        pull_request: {
            base: { ref: string };
        };
    };
};

/** What one major label rests on, as `<path>: <marker> (<net change>)` or a path rule. */
export type LabelEvidence = {
    label: string;
    reasons: string[];
};

export type LabelChanges = {
    add: string[];
    remove: string[];
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

export type IssueEvent = {
    event: string;
    label?: { name: string };
    actor?: { type?: string; login?: string } | null;
};

/** The identity `GITHUB_TOKEN` writes as, which is how this workflow adds its labels. */
const WORKFLOW_ACTOR = 'github-actions[bot]';

type ListEventsOptions = {
    owner: string;
    repo: string;
    issue_number: number;
    per_page: number;
};

type IssueLabel = { name: string };

type GitHubRestClient = PullRequestDiffClient & {
    paginate(
        route: (options: ListEventsOptions) => Promise<{ data: IssueEvent[] }>,
        options: ListEventsOptions,
    ): Promise<IssueEvent[]>;
    paginate(
        route: (options: ListEventsOptions) => Promise<{ data: IssueLabel[] }>,
        options: ListEventsOptions,
    ): Promise<IssueLabel[]>;
    rest: {
        issues: {
            listEvents(options: ListEventsOptions): Promise<{ data: IssueEvent[] }>;
            listLabelsOnIssue(options: ListEventsOptions): Promise<{ data: IssueLabel[] }>;
            addLabels(options: { owner: string; repo: string; issue_number: number; labels: string[] }): Promise<unknown>;
            removeLabel(options: { owner: string; repo: string; issue_number: number; name: string }): Promise<unknown>;
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
            flags.push({ name: name[1], default: false });
            continue;
        }

        const current = flags.at(-1);
        if (!current) {
            continue;
        }

        const major = line.match(/^\s*major:\s*(v\d+\.\d+\.0\.0)\b/i);
        if (major) {
            current.major = major[1];
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
    return flags.filter((flag) => /^v\d+\.\d+\.0\.0$/i.test(flag.name) && !flag.default).map((flag) => flag.name);
}

/**
 * The majors that have not shipped, oldest first — `['6.8', '6.9']`.
 * Recognises standalone version flags by name.
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

/**
 * Tests, specs, fixtures, prose and tooling name flags and deprecation tags to exercise or
 * describe them, never to change what a major does: `#[DisabledFeatures(['v6.8.0.0'])]` in a
 * test labelled every pull request that touched one, and `"majorFlags": ["v6.8.0.0"]` in the
 * public API snapshot fixtures did the same. UPGRADE files still count by path.
 */
export function isIgnoredPath(path: string): boolean {
    return (
        path.startsWith(EXCLUDED_PATH_PREFIX) ||
        path.startsWith('.agents/') ||
        path.startsWith('tests/') ||
        /\.(spec|test)\.[cm]?[jt]sx?$/.test(path) ||
        /\.(spec|test|fixtures?)\//.test(path) ||
        /\/Resources\/app\/(administration|storefront)\/test\//.test(path) ||
        // `eslint-rules/plugin-rules` ships to extension developers through the extension tooling.
        /\/Resources\/app\/administration\/(scripts|eslint-rules(?!\/plugin-rules\/))\//.test(path) ||
        path.includes('/__tests__/') ||
        path.includes('/__fixtures__/') ||
        /\.md$/i.test(path)
    );
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

/** The changed line's hunk as its own side of the diff sees it, and the line's position in it. */
function sideView(lines: string[], index: number): { view: string[]; position: number } {
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

    return { view, position };
}

/** Whether the changed line at `index` sits in a docblock that also says `@internal`. */
function isInInternalDocblock(lines: string[], index: number): boolean {
    const { view, position } = sideView(lines, index);

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

// `#[` opens a PHP attribute, not a comment.
const COMMENT_LINE = /^\s*(\*|\/\/|\/\*|#(?!\[)|\{#|<!--)/;
// HTML also ends a comment at `--!>`.
const CLOSED_LEADING_COMMENT = /^\s*(\/\*[\s\S]*?\*\/|\{#[\s\S]*?#\}|<!--[\s\S]*?--!?>)/;

/** A comment that closes on its own line hides nothing after it: `/* x *\/ if (…)` is code. */
function isCommentLine(content: string): boolean {
    const afterComment = content.replace(CLOSED_LEADING_COMMENT, '');

    return afterComment === content ? COMMENT_LINE.test(content) : afterComment.trim() === '';
}

const DEPRECATION_CALL = /(?:triggerDeprecationOrThrow|deprecated\w*Message|silence|silent)\s*\(|\bdeprecated\s*:\s*\{/g;

/**
 * Whether the match at `column` is a direct argument of a deprecation call or of a
 * `deprecated: { version }` declaration, possibly opened a few lines above. Such a flag
 * reports or silences a deprecation for the major, which is cleanup: every PHP deprecation
 * passes `'v6.8.0.0'` to `triggerDeprecationOrThrow()`, and counting that as behaviour put
 * `major/6.8` on every pull request that deprecated something.
 */
function isDeprecationArgument(lines: string[], index: number, column: number): boolean {
    const { view, position } = sideView(lines, index);
    const text = [
        ...view.slice(Math.max(0, position - 3), position).map((line) => line.slice(1)),
        view[position].slice(1, column),
    ].join('\n');
    const opener = [...text.matchAll(DEPRECATION_CALL)].at(-1);

    return opener !== undefined && !/[(){};]/.test(text.slice(opener.index + opener[0].length));
}

type MarkerCount = { kind: MarkerKind; marker: string; delta: number };

function markerBalance(section: string, markers: LineMarker[]): Map<string, MarkerCount> {
    const balance = new Map<string, MarkerCount>();
    const lines = section.split('\n');

    lines.forEach((line, index) => {
        if (!isChangedLine(line)) {
            return;
        }

        const isComment = isCommentLine(line.slice(1));

        markers.forEach(({ kind: markerKind, pattern }, markerIndex) => {
            for (const match of line.matchAll(pattern)) {
                if (markerKind === 'stabilisation' && isInInternalDocblock(lines, index)) {
                    continue;
                }

                // A comment documents the flag; only the deprecation and stability annotations live there.
                if (markerKind === 'behaviour' && isComment) {
                    continue;
                }

                const kind =
                    markerKind === 'behaviour' && isDeprecationArgument(lines, index, match.index) ? 'cleanup' : markerKind;
                // `V6_8_0_0` and `v6.8.0.0` are one flag, so switching the spelling changes nothing.
                const marker = match[0].replace(/\bV(\d+)_(\d+)_(\d+)_(\d+)\b/, 'v$1.$2.$3.$4');
                const key = `${kind}:${markerIndex}:${marker}`;
                const entry = balance.get(key) ?? { kind, marker: match[0], delta: 0 };
                entry.delta += line.startsWith('+') ? 1 : -1;
                balance.set(key, entry);
            }
        });
    });

    return balance;
}

type MajorLabelOptions = {
    diff: string;
    flags: FeatureFlag[];
    targetMajor: string;
    majorPaths: string[];
    /** The nearest in-flight major owns every signal that names no version. */
    isNextMajor: boolean;
};

type MajorAssessment = { labels: MajorLabels; reasons: { behaviour: string[]; cleanup: string[] } };

function assessMajorLabels(options: MajorLabelOptions): MajorAssessment {
    const { diff, flags, targetMajor, majorPaths, isNextMajor } = options;
    const allFiles = splitDiffByFile(diff);
    const files = allFiles.filter(({ path }) => path && !isIgnoredPath(path));
    const upgradeGuide = `UPGRADE-${targetMajor}.md`;
    const touchesUpgradeGuide = allFiles.some(({ path }) => path === upgradeGuide);
    const reasons: MajorAssessment['reasons'] = { behaviour: [], cleanup: [] };

    if (files.length === 0 && !touchesUpgradeGuide) {
        return { labels: { behaviour: false, cleanup: false }, reasons };
    }

    const version = escapeRegExp(targetMajor);
    // The Administration spells the same flag `V6_8_0_0`
    const versionFlag = [`v${targetMajor}.0.0`, `V${targetMajor.replace('.', '_')}_0_0`].map(escapeRegExp).join('|');
    const relatedFlags = flags
        .filter((flag) => flag.major === `v${targetMajor}.0.0`)
        .filter((flag) => !/^v\d+\.\d+\.\d+\.\d+$/i.test(flag.name))
        .map((flag) => escapeRegExp(flag.name));
    const flagAlternatives = [versionFlag, ...relatedFlags].join('|');
    const pathMatchers = isNextMajor ? majorPaths.map((glob) => ({ glob, matcher: globToRegExp(glob) })) : [];

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

    if (touchesUpgradeGuide) {
        reasons.behaviour.push(`${upgradeGuide} changed`);
    }

    for (const { path } of files) {
        if (isNextMajor && path === FEATURE_REGISTRY_PATH) {
            reasons.behaviour.push(`${path}: feature registry changed`);
        }
        const owner = pathMatchers.find(({ matcher }) => matcher.test(path));
        if (owner) {
            reasons.behaviour.push(`${path}: matches ${owner.glob} in ${MAJOR_PATHS_PATH}`);
        }
    }

    for (const { path, section } of files) {
        for (const { kind, marker, delta } of markerBalance(section, markers).values()) {
            if (delta === 0) {
                continue;
            }

            const reason = `${path}: ${marker} (${delta > 0 ? '+' : ''}${delta})`;
            if (kind === 'behaviour' || (kind === 'stabilisation' && delta < 0)) {
                reasons.behaviour.push(reason);
            } else {
                reasons.cleanup.push(reason);
            }
        }
    }

    return {
        labels: { behaviour: reasons.behaviour.length > 0, cleanup: reasons.cleanup.length > 0 },
        reasons,
    };
}

export function evaluateMajorLabels(options: MajorLabelOptions): MajorLabels {
    return assessMajorLabels(options).labels;
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

export function evidenceForDiff(options: { diff: string; flags: FeatureFlag[]; majorPaths: string[] }): LabelEvidence[] {
    const { diff, flags, majorPaths } = options;

    return resolveInFlightMajors(flags).flatMap((targetMajor, index) => {
        const { reasons } = assessMajorLabels({ diff, flags, targetMajor, majorPaths, isNextMajor: index === 0 });

        return [
            { label: `major/${targetMajor}`, reasons: reasons.behaviour },
            { label: `major/${targetMajor}-cleanup`, reasons: reasons.cleanup },
        ].filter((evidence) => evidence.reasons.length > 0);
    });
}

export function labelsForDiff(options: { diff: string; flags: FeatureFlag[]; majorPaths: string[] }): string[] {
    return evidenceForDiff(options).map(({ label }) => label);
}

const REASONS_LOGGED_PER_LABEL = 10;

export function formatEvidence({ label, reasons }: LabelEvidence): string {
    const shown = reasons.slice(0, REASONS_LOGGED_PER_LABEL);
    const more = reasons.length - shown.length;

    return [`${label}:`, ...shown.map((reason) => `  ${reason}`), ...(more > 0 ? [`  … and ${more} more`] : [])].join('\n');
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

    // `edited` also fires for a new title or description, which leave the diff as it was.
    if (context.payload.action === 'edited') {
        return context.payload.changes?.base !== undefined;
    }

    return [
        'opened',
        'reopened',
        'synchronize',
        'ready_for_review',
    ].includes(context.payload.action);
}

export function missingLabels(present: string[], wanted: string[]): string[] {
    return wanted.filter((label) => !present.includes(label));
}

/** The labels of the in-flight majors the pull request carries but the diff no longer earns. */
export function unsupportedLabels(present: string[], majors: string[], wanted: string[]): string[] {
    const managed = new Set(majors.flatMap((major) => [`major/${major}`, `major/${major}-cleanup`]));

    return present.filter((label) => managed.has(label) && !wanted.includes(label));
}

/** Only a label this workflow added is its to take back; the latest addition decides. */
export function addedByWorkflow(labels: string[], events: IssueEvent[]): string[] {
    return labels.filter((label) => {
        const added = events.filter((event) => event.event === 'labeled' && event.label?.name === label).at(-1);

        return added?.actor?.login === WORKFLOW_ACTOR;
    });
}

/**
 * Whoever removed a label has overruled the detection for good. A bot never has: this
 * workflow takes back a label the diff stopped earning, and `srvaroa/labeler` rewrites the
 * whole label set from a snapshot taken before this workflow added its label. Both removals
 * show up as `github-actions[bot]`.
 */
export function withoutRemovedLabels(labels: string[], events: IssueEvent[]): string[] {
    const removed = new Set(
        events.filter(({ event, actor }) => event === 'unlabeled' && actor?.type !== 'Bot').map(({ label }) => label?.name),
    );

    return labels.filter((label) => !removed.has(label));
}

const NO_CHANGES: LabelChanges = { add: [], remove: [] };

export async function detectMajorLabels(
    { github, core, context }: { github: GitHubRestClient; core: CoreLogger; context: PullRequestContext },
    readFile: (path: string) => string,
): Promise<LabelChanges> {
    if (!shouldDetect(context)) {
        core.info(
            `skipping major-label detection: ${context.eventName}/${context.payload.action} into ${context.payload.pull_request.base.ref} is not a trigger`,
        );

        return NO_CHANGES;
    }

    // Base ref, never the PR head: a fork must not be able to edit the registry or the path
    // map that judge it. A pull request that adds a flag is covered by the registry path hit.
    const flags = parseFeatureRegistry(readFile(FEATURE_REGISTRY_PATH));
    const majors = resolveInFlightMajors(flags);

    if (majors.length === 0) {
        core.info('no in-flight major in the registry, nothing to label');

        return NO_CHANGES;
    }

    const { diff, complete } = await fetchPullRequestDiff({ github, core, context });
    const majorPaths = parseMajorPaths(readFile(MAJOR_PATHS_PATH));
    const evidence = evidenceForDiff({ diff, flags, majorPaths });
    const wanted = evidence.map(({ label }) => label);
    const issue = {
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: context.payload.pull_request.number,
        per_page: 100,
    };
    // Read live, not from the payload: an earlier run for a quick push may have changed them
    // since this event fired, and a stale "present" would keep a label it just removed off.
    const present = (await github.paginate(github.rest.issues.listLabelsOnIssue, issue)).map(({ name }) => name);
    const missing = missingLabels(present, wanted);
    const unsupported = unsupportedLabels(present, majors, wanted);

    core.info(
        wanted.length === 0
            ? `no markers for ${majors.join(', ')} in the diff`
            : ['markers found:', ...evidence.map(formatEvidence)].join('\n'),
    );

    if (!complete && unsupported.length > 0) {
        core.info('the diff was not read in full, so existing major labels stay');
    }

    const removable = complete ? unsupported : [];

    if (missing.length === 0 && removable.length === 0) {
        return NO_CHANGES;
    }

    // The labels show what is there now, not who added or removed them.
    const events = await github.paginate(github.rest.issues.listEvents, issue);
    const add = withoutRemovedLabels(missing, events);
    const remove = addedByWorkflow(removable, events);
    const overruled = missing.filter((label) => !add.includes(label));
    const keptManual = removable.filter((label) => !remove.includes(label));

    if (overruled.length > 0) {
        core.info(`not re-adding ${overruled.join(', ')}: removed from this pull request before`);
    }
    if (keptManual.length > 0) {
        core.info(`keeping ${keptManual.join(', ')}: not added by this workflow`);
    }
    if (remove.length > 0) {
        core.info(`removing ${remove.join(', ')}: no marker in the diff supports it any more`);
    }

    return { add, remove };
}

export async function applyMajorLabels(
    { github, context }: { github: GitHubRestClient; context: PullRequestContext },
    { add, remove }: LabelChanges,
): Promise<void> {
    const issue = { owner: context.repo.owner, repo: context.repo.repo, issue_number: context.payload.pull_request.number };

    if (add.length > 0) {
        await github.rest.issues.addLabels({ ...issue, labels: add });
    }

    for (const name of remove) {
        try {
            await github.rest.issues.removeLabel({ ...issue, name });
        } catch (error) {
            // Someone took it off between the payload and this call.
            if ((error as { status?: number }).status !== 404) {
                throw error;
            }
        }
    }
}
