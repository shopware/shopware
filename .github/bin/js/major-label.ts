/**
 * Label a pull request for the pending major release it affects.
 *
 * Two labels, because the markers mean two different things. `major/<version>` says the
 * pull request changes what the major does: it documents an upgrade step, stabilises an
 * experimental API, narrows a signature, or moves a major feature flag.
 * `major/<version>-cleanup` says it only leaves something behind to delete — an
 * `@deprecated tag:` annotation and nothing else. A pull request can earn both.
 *
 * Most work for the next major ships inside a `6.7.x` minor behind a flag that defaults to
 * off, so the milestone label — which records the version that ships a change — cannot
 * carry this. These labels are the orthogonal axis and must never be milestone labels.
 *
 * The target version is derived, never hardcoded: the lowest `vX.Y.0.0` flag registered
 * `major: true` with `default: false` is the major that has not shipped yet. Flags already
 * defaulting to true have flipped and are not pending.
 *
 * Known gap: a pull request that changes flagged behaviour inside a shared file without
 * touching the flag line carries no marker and no path. `.github/major-paths.yml` closes
 * this for features that own a namespace; nothing closes it for the rest.
 */

export const FEATURE_REGISTRY_PATH = 'src/Core/Framework/Resources/config/packages/feature.yaml';
export const MAJOR_PATHS_PATH = '.github/major-paths.yml';

/** Tooling quotes flag names and version strings without changing major behavior. */
export const EXCLUDED_PATH_PREFIX = '.github/';

export type FeatureFlag = {
    name: string;
    major: boolean;
    default: boolean;
};

export type MajorLabels = {
    behaviour: boolean;
    cleanup: boolean;
};

type DiffFileSection = {
    path: string;
    section: string;
};

type PullRequestDetectionContext = {
    eventName: string;
    payload: {
        action: string;
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
            labels?: Array<{ name: string }>;
        };
    };
};

type GitHubRestClient = {
    rest: {
        pulls: {
            get(options: {
                owner: string;
                repo: string;
                pull_number: number;
                mediaType: { format: 'diff' };
            }): Promise<{ data: unknown }>;
        };
        issues: {
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

/** `v6.8.0.0` -> `6.8`. Null when no major version flag is pending. */
export function resolveTargetMajor(flags: FeatureFlag[]): string | null {
    const versions = pendingMajorFlags(flags)
        .map((name) => name.match(/^v(\d+)\.(\d+)\.0\.0$/))
        .filter((match): match is RegExpMatchArray => match !== null)
        .map((match) => ({ major: Number(match[1]), minor: Number(match[2]) }))
        .sort((a, b) => a.major - b.major || a.minor - b.minor);

    const next = versions.at(0);

    return next ? `${next.major}.${next.minor}` : null;
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

function splitDiffByFile(diff: string): DiffFileSection[] {
    return diff
        .split(/^diff --git /m)
        .slice(1)
        .map((section) => {
            const path = section.match(/^a\/\S+ b\/(\S+)/);

            return { path: path ? path[1] : '', section };
        });
}

function changedLines(section: string): string[] {
    return section
        .split('\n')
        .filter(
            (line) =>
                (line.startsWith('+') || line.startsWith('-')) &&
                !line.startsWith('+++') &&
                !line.startsWith('---'),
        );
}

export function evaluateMajorLabels(options: {
    diff: string;
    flags: FeatureFlag[];
    targetMajor: string;
    majorPaths: string[];
}): MajorLabels {
    const { diff, flags, targetMajor, majorPaths } = options;
    const files = splitDiffByFile(diff).filter(({ path }) => path && !path.startsWith(EXCLUDED_PATH_PREFIX));

    if (files.length === 0) {
        return { behaviour: false, cleanup: false };
    }

    const version = escapeRegExp(targetMajor);
    const flagNames = pendingMajorFlags(flags).map(escapeRegExp);
    const pathMatchers = majorPaths.map(globToRegExp);

    // `(?!\d)` keeps v6.8.0 from matching a v6.8.01 that a future scheme might introduce
    const flagAlternatives = flagNames.join('|');
    const behaviourLineMarkers = [
        new RegExp(`stableVersion:v${version}\\.0(?!\\d)`),
        new RegExp(`version:\\s*'v${version}\\.0'`),
        // A flag name only counts where a consuming construct delimits it. Backtick-wrapped
        // prose is deliberately excluded: release notes describe flags without changing them.
        ...(flagNames.length > 0
            ? [
                  new RegExp(`['"](${flagAlternatives})['"]`),
                  new RegExp(`<flag>(${flagAlternatives})</flag>`),
                  new RegExp(`\\b(${flagAlternatives})\\s*:`),
              ]
            : []),
    ];
    const cleanupLineMarker = new RegExp(`tag:v${version}\\.0(?!\\d)`);

    const behaviourPath = files.some(
        ({ path }) =>
            path === `UPGRADE-${targetMajor}.md` ||
            path === FEATURE_REGISTRY_PATH ||
            pathMatchers.some((matcher) => matcher.test(path)),
    );

    const lines = files.flatMap(({ section }) => changedLines(section));

    return {
        behaviour: behaviourPath || lines.some((line) => behaviourLineMarkers.some((marker) => marker.test(line))),
        cleanup: lines.some((line) => cleanupLineMarker.test(line)),
    };
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

// All run conditions live here so they are unit-testable instead of an untestable YAML expression.
export function shouldDetect(context: PullRequestDetectionContext): boolean {
    if (context.eventName !== 'pull_request_target') {
        return false;
    }

    return ['opened', 'reopened', 'synchronize', 'ready_for_review'].includes(context.payload.action);
}

/** Labels are only ever added: an author who removes one has overruled the detection. */
export function missingLabels(context: PullRequestContext, wanted: string[]): string[] {
    const present = new Set((context.payload.pull_request.labels ?? []).map((label) => label.name));

    return wanted.filter((label) => !present.has(label));
}

export async function detectMajorLabels(
    { github, core, context }: { github: GitHubRestClient; core: CoreLogger; context: PullRequestContext },
    readFile: (path: string) => string,
): Promise<string[]> {
    if (!shouldDetect(context)) {
        core.info(`skipping major-label detection: ${context.eventName}/${context.payload.action} is not a trigger`);

        return [];
    }

    // Base ref, never the PR head: a fork must not be able to edit the registry or the path
    // map that judge it. A pull request that adds a flag is covered by the registry path hit.
    const flags = parseFeatureRegistry(readFile(FEATURE_REGISTRY_PATH));
    const targetMajor = resolveTargetMajor(flags);

    if (!targetMajor) {
        core.info('no pending major version flag in the registry, nothing to label');

        return [];
    }

    const { data: diff } = await github.rest.pulls.get({
        owner: context.repo.owner,
        repo: context.repo.repo,
        pull_number: context.payload.pull_request.number,
        mediaType: { format: 'diff' },
    });

    const majorPaths = parseMajorPaths(readFile(MAJOR_PATHS_PATH));
    const evaluated = evaluateMajorLabels({ diff: String(diff), flags, targetMajor, majorPaths });
    const wanted = labelNamesFor(targetMajor, evaluated);
    const missing = missingLabels(context, wanted);

    core.info(
        wanted.length === 0
            ? `no ${targetMajor} markers in the diff`
            : `${targetMajor} markers found: ${wanted.join(', ')}${missing.length === 0 ? ' (already applied)' : ''}`,
    );

    return missing;
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
