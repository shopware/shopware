/**
 * Auto-apply the relevant major-test labels when a PR touches major feature flags.
 *
 * Detection is registry-driven: every flag registered with `major: true` in
 * `src/Core/Framework/Resources/config/packages/feature.yaml` counts, read from the
 * PR's own head so flags added by the PR itself are covered. Every consuming
 * construct (`Feature::isActive`, `skipTestIf(In)Active`, `withFeatureEnabled/Disabled`,
 * `#[DisabledFeatures]`, `triggerDeprecationOrThrow`) quotes the flag name, so one
 * quoted-name pattern covers them all.
 *
 * Beyond flag names, any `6.x.y` version string in a changed line counts: it covers
 * `@deprecated tag:v6.9.0` annotations and the BC-change attributes
 * (`#[ReturnTypeNarrowing(version: 'v6.8.0', ...)]`) alike — the three constructs
 * use three different version formats, so the shared digits are the one stable
 * signal. The imprecision is accepted: mentioning a version in changed code is a
 * good-enough reason to run the major matrix, and the known noise (e.g. changelog
 * release headings) is rare — see the discussion on the introducing PR.
 *
 * The PHP arm retains that repository-wide marker detection. The Administration
 * Jest arm uses the same markers only in Administration source and test files;
 * a feature-registry change enables both arms because it changes both baselines.
 */

export const FEATURE_REGISTRY_PATH = 'src/Core/Framework/Resources/config/packages/feature.yaml';
export const ADMINISTRATION_APP_PATH = 'src/Administration/Resources/app/administration/';
export const ADMINISTRATION_SOURCE_PATH = `${ADMINISTRATION_APP_PATH}src/`;
export const ADMINISTRATION_TEST_PATH = `${ADMINISTRATION_APP_PATH}test/`;

type PullRequestLabel = {
    name: string;
};

type PullRequestDetectionContext = {
    eventName: string;
    repo: {
        owner: string;
        repo: string;
    };
    payload: {
        action: string;
        pull_request: {
            head: {
                repo: {
                    full_name: string;
                };
            };
            labels?: PullRequestLabel[];
        };
    };
};

type PullRequestContext = PullRequestDetectionContext & {
    payload: {
        action: string;
        pull_request: {
            number: number;
            head: {
                sha: string;
                repo: {
                    full_name: string;
                };
            };
            labels?: PullRequestLabel[];
        };
    };
};

type GitHubRestClient = {
    rest: {
        repos: {
            getContent(options: {
                owner: string;
                repo: string;
                path: string;
                ref: string;
                mediaType: { format: 'raw' };
            }): Promise<{ data: unknown }>;
        };
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

type DetectionToolkit = {
    github: GitHubRestClient;
    core: CoreLogger;
    context: PullRequestContext;
};

type LabelToolkit = {
    github: GitHubRestClient;
    context: PullRequestContext;
};

type DiffFileSection = {
    path: string;
    section: string;
};

export type MajorTestArms = {
    php: boolean;
    js: boolean;
};

// All run conditions beyond the workflow-level `if: github.event_name == 'pull_request'`
// live here so they are unit-testable instead of being an untestable YAML expression.
export function shouldDetect(context: PullRequestDetectionContext): boolean {
    if (context.eventName !== 'pull_request') {
        return false;
    }

    if (context.payload.action !== 'opened' && context.payload.action !== 'synchronize') {
        return false;
    }

    const pullRequest = context.payload.pull_request;
    // fork PRs cannot mint the app token needed for the label to trigger the matrix
    if (pullRequest.head.repo.full_name !== `${context.repo.owner}/${context.repo.repo}`) {
        return false;
    }

    const labels = (pullRequest.labels ?? []).map((label) => label.name);

    return !labels.includes('major-tests') && (!labels.includes('major-php') || !labels.includes('major-js'));
}

export function parseMajorFlags(registryYaml: string): string[] {
    const majorFlags: string[] = [];
    let currentFlag: string | null = null;

    for (const line of registryYaml.split('\n')) {
        const name = line.match(/^\s*-\s*name:\s*(\S+)/);
        if (name) {
            currentFlag = name[1];
        } else if (currentFlag && /^\s*major:\s*(true|false)\b/.test(line)) {
            if (line.includes('true')) {
                majorFlags.push(currentFlag);
            }
            currentFlag = null;
        }
    }

    return majorFlags;
}

/**
 * Tooling and workflow code quotes flag names without changing major behavior,
 * so changes under .github/ never count as a hit.
 */
export const EXCLUDED_PATH_PREFIX = '.github/';

export function splitDiffByFile(diff: string): DiffFileSection[] {
    return diff
        .split(/^diff --git /m)
        .slice(1)
        .map((section) => {
            const path = section.match(/^a\/\S+ b\/(\S+)/);

            return { path: path ? path[1] : '', section };
        });
}

export function hasMajorMarkers(diff: string, majorFlags: string[]): boolean {
    const files = splitDiffByFile(diff).filter(({ path }) => !path.startsWith(EXCLUDED_PATH_PREFIX));

    if (files.some(({ path }) => path === FEATURE_REGISTRY_PATH)) {
        return true;
    }

    // added or removed lines only — deleting a legacy flag branch is a major-behavior change too
    const changedLines = files.flatMap(({ section }) => section.split('\n').filter((line) => /^[+-][^+-]/.test(line)));

    if (changedLines.length === 0) {
        return false;
    }

    const escaped = majorFlags.map((flag) => flag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const markers = [
        new RegExp(`['"](${escaped.join('|')})['"]`),
        // any 6.x.y version string: @deprecated tag:v6.9.0, BC attributes (version: 'v6.8.0'), ...
        /6\.\d+\.\d/,
    ];

    return changedLines.some((line) => markers.some((marker) => marker.test(line)));
}

export function hasMajorJsMarkers(diff: string, majorFlags: string[]): boolean {
    const files = splitDiffByFile(diff);

    if (files.some(({ path }) => path === FEATURE_REGISTRY_PATH)) {
        return true;
    }

    const administrationFiles = files.filter(
        ({ path }) => path.startsWith(ADMINISTRATION_SOURCE_PATH) || path.startsWith(ADMINISTRATION_TEST_PATH),
    );
    const changedLines = administrationFiles.flatMap(({ section }) =>
        section.split('\n').filter((line) => /^[+-][^+-]/.test(line)),
    );

    if (changedLines.length === 0) {
        return false;
    }

    const escaped = majorFlags.map((flag) => flag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const markers = [
        new RegExp(`['"](${escaped.join('|')})['"]`),
        /6\.\d+\.\d/,
    ];

    return changedLines.some((line) => markers.some((marker) => marker.test(line)));
}

export function labelsForMajorTestArms(arms: MajorTestArms, existingLabels: PullRequestLabel[] = []): string[] {
    const existing = new Set(existingLabels.map((label) => label.name));

    return [
        arms.php && !existing.has('major-php') ? 'major-php' : null,
        arms.js && !existing.has('major-js') ? 'major-js' : null,
    ].filter((label): label is string => label !== null);
}

export async function detectMajorTestArms({ github, core, context }: DetectionToolkit): Promise<MajorTestArms> {
    if (!shouldDetect(context)) {
        core.info('skipping major-flag detection: event, fork head, or existing label rules it out');

        return { php: false, js: false };
    }

    const { data: registry } = await github.rest.repos.getContent({
        owner: context.repo.owner,
        repo: context.repo.repo,
        path: FEATURE_REGISTRY_PATH,
        ref: context.payload.pull_request.head.sha,
        mediaType: { format: 'raw' },
    });

    const majorFlags = parseMajorFlags(String(registry));

    const { data: diff } = await github.rest.pulls.get({
        owner: context.repo.owner,
        repo: context.repo.repo,
        pull_number: context.payload.pull_request.number,
        mediaType: { format: 'diff' },
    });

    const arms = {
        php: hasMajorMarkers(String(diff), majorFlags),
        js: hasMajorJsMarkers(String(diff), majorFlags),
    };
    core.info(
        arms.php || arms.js
            ? `major marker found in the diff (${majorFlags.length} registered major flags; php=${arms.php}; js=${arms.js})`
            : 'no major markers in the diff',
    );

    return arms;
}

// Expects a token able to trigger the `labeled` workflow run.
export async function addMajorTestLabels({ github, context, labels }: LabelToolkit & { labels: string[] }): Promise<void> {
    await github.rest.issues.addLabels({
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: context.payload.pull_request.number,
        labels,
    });
}
