/**
 * Auto-apply the `major-php` label when a PR touches major feature flags.
 *
 * Detection is registry-driven: every flag registered with `major: true` in
 * `src/Core/Framework/Resources/config/packages/feature.yaml` counts, read from the
 * PR's own head so flags added by the PR itself are covered. Every consuming
 * construct (`Feature::isActive`, `skipTestIf(In)Active`, `withFeatureEnabled/Disabled`,
 * `#[DisabledFeatures]`, `triggerDeprecationOrThrow`) quotes the flag name, so one
 * quoted-name pattern covers them all.
 *
 * BC-change attributes (`#[ReturnTypeNarrowing(...)]`, `#[BecomesFinal(...)]`, ...)
 * are detected the same way: the attribute class names are read from the
 * `Framework/Deprecation/BCChange` directory listing at the PR's head, so
 * attributes added later are covered without touching this script. They mark
 * code whose contract changes at the next major, and their version strings
 * (`'v6.8.0'`) match neither the flag names (`'v6.8.0.0'`) nor the
 * `@deprecated tag:` pattern, so they need their own marker.
 */

export const FEATURE_REGISTRY_PATH = 'src/Core/Framework/Resources/config/packages/feature.yaml';

export const BC_CHANGE_ATTRIBUTES_PATH = 'src/Core/Framework/Deprecation/BCChange';

/**
 * All run conditions beyond the workflow-level `if: github.event_name == 'pull_request'`
 * live here so they are unit-testable instead of being an untestable YAML expression.
 *
 * @param {object} context github-script context
 * @returns {boolean}
 */
export function shouldDetect(context) {
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

    return !labels.includes('major-php') && !labels.includes('major-tests');
}

/**
 * @param {string} registryYaml
 * @returns {string[]} names of flags registered with `major: true`
 */
export function parseMajorFlags(registryYaml) {
    const majorFlags = [];
    let currentFlag = null;

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
 * The directory listing contains the attribute classes plus the marker interfaces
 * they implement; including the interface names in the pattern is harmless because
 * interfaces never appear as `#[Name(` usages.
 *
 * @param {Array<{name: string, type: string}>} entries getContent directory listing
 * @returns {string[]} class names of the BC-change attributes
 */
export function parseBCChangeAttributes(entries) {
    return entries
        .filter((entry) => entry.type === 'file' && entry.name.endsWith('.php'))
        .map((entry) => entry.name.slice(0, -'.php'.length));
}

/**
 * Tooling and workflow code quotes flag names without changing major behavior,
 * so changes under .github/ never count as a hit.
 */
export const EXCLUDED_PATH_PREFIX = '.github/';

/**
 * @param {string} diff unified diff of the PR
 * @returns {Array<{path: string, section: string}>}
 */
function splitDiffByFile(diff) {
    return diff
        .split(/^diff --git /m)
        .slice(1)
        .map((section) => {
            const path = section.match(/^a\/\S+ b\/(\S+)/);

            return { path: path ? path[1] : '', section };
        });
}

/**
 * @param {string} diff unified diff of the PR
 * @param {string[]} majorFlags
 * @param {string[]} bcChangeAttributes
 * @returns {boolean}
 */
export function hasMajorMarkers(diff, majorFlags, bcChangeAttributes = []) {
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
        /@deprecated\s+tag:v6\.\d+\.\d+/,
    ];

    if (bcChangeAttributes.length > 0) {
        // matches plain and qualified usages: #[ReturnTypeNarrowing(, #[BCChange\ReturnTypeNarrowing(
        markers.push(new RegExp(`#\\[(?:[A-Za-z0-9_]+\\\\)*(?:${bcChangeAttributes.join('|')})\\(`));
    }

    return changedLines.some((line) => markers.some((marker) => marker.test(line)));
}

/**
 * @param {{github: object, core: object, context: object}} toolkit
 * @returns {Promise<boolean>}
 */
export async function detectMajorFlagUsage({ github, core, context }) {
    if (!shouldDetect(context)) {
        core.info('skipping major-flag detection: event, fork head, or existing label rules it out');

        return false;
    }

    const { data: registry } = await github.rest.repos.getContent({
        owner: context.repo.owner,
        repo: context.repo.repo,
        path: FEATURE_REGISTRY_PATH,
        ref: context.payload.pull_request.head.sha,
        mediaType: { format: 'raw' },
    });

    const majorFlags = parseMajorFlags(String(registry));

    const { data: attributeDir } = await github.rest.repos.getContent({
        owner: context.repo.owner,
        repo: context.repo.repo,
        path: BC_CHANGE_ATTRIBUTES_PATH,
        ref: context.payload.pull_request.head.sha,
    });

    const bcChangeAttributes = parseBCChangeAttributes(attributeDir);

    const { data: diff } = await github.rest.pulls.get({
        owner: context.repo.owner,
        repo: context.repo.repo,
        pull_number: context.payload.pull_request.number,
        mediaType: { format: 'diff' },
    });

    const hit = hasMajorMarkers(String(diff), majorFlags, bcChangeAttributes);
    core.info(
        hit
            ? `major marker found in the diff (${majorFlags.length} registered major flags, ${bcChangeAttributes.length} BC-change attributes)`
            : 'no major markers in the diff',
    );

    return hit;
}

/**
 * @param {{github: object, context: object}} toolkit expects a token able to trigger the `labeled` workflow run
 */
export async function addMajorPhpLabel({ github, context }) {
    await github.rest.issues.addLabels({
        owner: context.repo.owner,
        repo: context.repo.repo,
        issue_number: context.payload.pull_request.number,
        labels: ['major-php'],
    });
}
