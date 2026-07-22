/**
 * Auto-apply the `major-php` label when a PR touches major feature flags.
 *
 * Detection is registry-driven: every flag registered with `major: true` in
 * `src/Core/Framework/Resources/config/packages/feature.yaml` counts, read from the
 * PR's own head so flags added by the PR itself are covered. Every consuming
 * construct (`Feature::isActive`, `skipTestIf(In)Active`, `withFeatureEnabled/Disabled`,
 * `#[DisabledFeatures]`, `triggerDeprecationOrThrow`) quotes the flag name, so one
 * quoted-name pattern covers them all.
 */

export const FEATURE_REGISTRY_PATH = 'src/Core/Framework/Resources/config/packages/feature.yaml';

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
 * @param {string} diff unified diff of the PR
 * @param {string[]} majorFlags
 * @returns {boolean}
 */
export function hasMajorMarkers(diff, majorFlags) {
    // added or removed lines only — deleting a legacy flag branch is a major-behavior change too
    const changedLines = diff.split('\n').filter((line) => /^[+-][^+-]/.test(line));

    if (changedLines.length === 0) {
        return false;
    }

    if (diff.includes(FEATURE_REGISTRY_PATH)) {
        return true;
    }

    const escaped = majorFlags.map((flag) => flag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const markers = [
        new RegExp(`['"](${escaped.join('|')})['"]`),
        /@deprecated\s+tag:v6\.\d+\.\d+/,
    ];

    return changedLines.some((line) => markers.some((marker) => marker.test(line)));
}

/**
 * @param {{github: object, core: object, context: object}} toolkit
 * @returns {Promise<boolean>}
 */
export async function detectMajorFlagUsage({ github, core, context }) {
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

    const hit = hasMajorMarkers(String(diff), majorFlags);
    core.info(
        hit
            ? `major-flag marker found in the diff (${majorFlags.length} registered major flags)`
            : 'no major-flag markers in the diff',
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
