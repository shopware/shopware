import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    FEATURE_REGISTRY_PATH,
    MAJOR_PATHS_PATH,
    detectMajorLabels,
    diffFromFiles,
    evaluateMajorLabels,
    isDiffTooLarge,
    globToRegExp,
    labelNamesFor,
    labelsForDiff,
    missingLabels,
    parseFeatureRegistry,
    parseMajorPaths,
    pendingMajorFlags,
    resolveInFlightMajors,
    isReleaseBranch,
    shouldDetect,
    withoutRemovedLabels,
} from './major-label.ts';

const REGISTRY = `shopware:
  feature:
    flags:
      - name: v6.7.0.0
        default: true
        toggleable: false
      - name: v6.8.0.0
        default: false
        toggleable: false
      - name: WEBHOOKS_REWORK
        default: false
        major: v6.8.0.0
        toggleable: true
      - name: TELEMETRY_METRICS
        default: false
        toggleable: true
`;

const FLAGS = parseFeatureRegistry(REGISTRY);

const PATH_MAP = `# comment mentioning **/Decoy/** which must not become a glob
DOCUMENT_GENERATION_REWORK:
  - "**/DocumentV2/**"
  - "**/documentV2*"

WEBHOOKS_REWORK:
  - "src/Core/Framework/Webhook/Outbox/**"

JSON_LD_DATA: []
`;

const PATHS = parseMajorPaths(PATH_MAP);

const diffFor = (path: string, hunk: string): string => `diff --git a/${path} b/${path}
index 0000000..1111111 100644
--- a/${path}
+++ b/${path}
@@ -1,1 +1,1 @@
${hunk}`;

const evaluate = (path: string, hunk: string) =>
    evaluateMajorLabels({
        diff: diffFor(path, hunk),
        flags: FLAGS,
        targetMajor: '6.8',
        majorPaths: PATHS,
        isNextMajor: true,
    });

test('parseFeatureRegistry reads name, major and default per flag', () => {
    assert.deepEqual(FLAGS, [
        { name: 'v6.7.0.0', default: true },
        { name: 'v6.8.0.0', default: false },
        { name: 'WEBHOOKS_REWORK', major: 'v6.8.0.0', default: false },
        { name: 'TELEMETRY_METRICS', default: false },
    ]);
});

test('pendingMajorFlags includes only unshipped root majors', () => {
    assert.deepEqual(pendingMajorFlags(FLAGS), ['v6.8.0.0']);
});

test('resolveInFlightMajors derives the version from the pending major flag', () => {
    assert.deepEqual(resolveInFlightMajors(FLAGS), ['6.8']);
});

test('resolveInFlightMajors returns every unreleased major, oldest first', () => {
    const flags = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: v6.9.0.0
            default: false
          - name: v6.8.0.0
            default: false
`);
    assert.deepEqual(resolveInFlightMajors(flags), ['6.8', '6.9']);
});

test('resolveInFlightMajors is empty once every major flag has flipped', () => {
    const flags = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: v6.8.0.0
            default: true
`);
    assert.deepEqual(resolveInFlightMajors(flags), []);
});

test('parseMajorPaths reads globs and ignores comments and empty lists', () => {
    assert.deepEqual(PATHS, [
        '**/DocumentV2/**',
        '**/documentV2*',
        'src/Core/Framework/Webhook/Outbox/**',
    ]);
});

test('globToRegExp keeps * inside one segment and lets ** cross them', () => {
    assert.equal(globToRegExp('**/documentV2*').test('src/Administration/app/documentV2.api.service.ts'), true);
    assert.equal(globToRegExp('**/DocumentV2/**').test('src/Core/Checkout/DocumentV2/Config/DocumentConfig.php'), true);
    assert.equal(globToRegExp('src/Core/*/Thing.php').test('src/Core/Checkout/Nested/Thing.php'), false);
    assert.equal(globToRegExp('**/DocumentV2/**').test('src/Core/Checkout/Document/Renderer.php'), false);
});

test('an UPGRADE entry for the target major is a behaviour change', () => {
    assert.deepEqual(evaluate('UPGRADE-6.8.md', '+## Something breaks'), { behaviour: true, cleanup: false });
});

test('an UPGRADE entry for a later major is not', () => {
    assert.deepEqual(evaluate('UPGRADE-6.9.md', '+## Something breaks'), { behaviour: false, cleanup: false });
});

test('removing an experimental annotation stabilises the API, a behaviour change', () => {
    assert.deepEqual(
        evaluate('src/Core/Checkout/Cart/Cart.php', '- * @experimental stableVersion:v6.8.0 feature:CACHE_REWORK'),
        { behaviour: true, cleanup: false },
    );
});

test('adding an experimental annotation leaves the stabilisation behind as cleanup', () => {
    assert.deepEqual(
        evaluate('src/Core/Checkout/Cart/Cart.php', '+ * @experimental stableVersion:v6.8.0 feature:CACHE_REWORK'),
        { behaviour: false, cleanup: true },
    );
});

test('an experimental annotation in an internal docblock counts for nothing', () => {
    for (const docblock of [
        '+/**\n+ * @experimental stableVersion:v6.8.0\n+ *\n+ * @internal\n+ */',
        '+/**\n+ * @internal\n+ * @experimental stableVersion:v6.8.0\n+ */',
        ' /**\n- * @experimental stableVersion:v6.8.0\n  * @internal\n  */',
    ]) {
        assert.deepEqual(evaluate('src/Core/Framework/Mcp/Resolver.php', docblock), { behaviour: false, cleanup: false });
    }
});

test('an internal docblock does not shield the next one', () => {
    assert.deepEqual(
        evaluate('src/Core/Framework/Mcp/Resolver.php', '+/**\n+ * @internal\n+ */\n+/**\n+ * @experimental stableVersion:v6.8.0\n+ */'),
        { behaviour: false, cleanup: true },
    );
});

test('a flag line that is only reworded is not a change to the flag', () => {
    assert.deepEqual(
        evaluate(
            'tests/integration/Core/Framework/Webhook/Service/WebhookManagerTest.php',
            "-        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($client): void {\n+        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($client, $bus): void {",
        ),
        { behaviour: false, cleanup: false },
    );
});

test('swapping one flag for another on the same line is a change', () => {
    assert.deepEqual(
        evaluate('src/Core/Cart.php', "-        if (Feature::isActive('v6.8.0.0')) {\n+        if (Feature::isActive('WEBHOOKS_REWORK')) {"),
        { behaviour: true, cleanup: false },
    );
});

test('moving a flag check to another file is a change in both', () => {
    const diff =
        diffFor('src/Core/A.php', "-        if (Feature::isActive('WEBHOOKS_REWORK')) {") +
        '\n' +
        diffFor('src/Core/B.php', "+        if (Feature::isActive('WEBHOOKS_REWORK')) {");
    assert.deepEqual(evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS, isNextMajor: true }), {
        behaviour: true,
        cleanup: false,
    });
});

test('a rewritten deprecation message leaves no new cleanup behind', () => {
    assert.deepEqual(
        evaluate('src/Core/Framework/Feature.php', '-     * @deprecated tag:v6.8.0 - Will be removed\n+     * @deprecated tag:v6.8.0 - Will be removed, use Bar'),
        { behaviour: false, cleanup: false },
    );
});

test('a BC-change attribute for the target major is a behaviour change', () => {
    assert.deepEqual(
        evaluate('src/Core/Framework/Context.php', "+    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]"),
        { behaviour: true, cleanup: false },
    );
});

test('a BC-change attribute for a later major is not', () => {
    assert.deepEqual(evaluate('src/Core/Framework/Context.php', "+    #[BecomesFinal(version: 'v6.9.0')]"), {
        behaviour: false,
        cleanup: false,
    });
});

test('a pending major flag in a changed line is a behaviour change', () => {
    assert.deepEqual(evaluate('src/Core/Cart.php', "+        if (Feature::isActive('WEBHOOKS_REWORK')) {"), {
        behaviour: true,
        cleanup: false,
    });
});

test('a flag inside an XML config element is a behaviour change', () => {
    assert.deepEqual(evaluate('src/Core/System/Resources/config/listing.xml', '+            <flag>WEBHOOKS_REWORK</flag>'), {
        behaviour: true,
        cleanup: false,
    });
});

test('a flag as an object key is a behaviour change', () => {
    assert.deepEqual(
        evaluate('src/Storefront/Resources/app/storefront/test/plugin/x.test.js', '+    Feature.init({ WEBHOOKS_REWORK: true });'),
        { behaviour: true, cleanup: false },
    );
});

test('a flag named in backticked prose is not — release notes describe, they do not change', () => {
    assert.deepEqual(
        evaluate('RELEASE_INFO-6.7.md', '+Rolled out behind the `WEBHOOKS_REWORK` feature flag.'),
        { behaviour: false, cleanup: false },
    );
});

test('a flag mentioned in a deprecation docblock earns cleanup through the tag alone', () => {
    assert.deepEqual(
        evaluate('src/Core/Framework/Webhook/Service/WebhookManager.php', '+ * @deprecated tag:v6.8.0 - pre-WEBHOOKS_REWORK path; will be removed.'),
        { behaviour: false, cleanup: true },
    );
});

test('a flag that already flipped is not', () => {
    assert.deepEqual(evaluate('src/Core/Cart.php', "+        if (Feature::isActive('v6.7.0.0')) {"), {
        behaviour: false,
        cleanup: false,
    });
});

test('a non-major flag is not', () => {
    assert.deepEqual(evaluate('src/Core/Telemetry.php', "+        if (Feature::isActive('TELEMETRY_METRICS')) {"), {
        behaviour: false,
        cleanup: false,
    });
});

test('editing the flag registry is a behaviour change whatever the line says', () => {
    assert.deepEqual(evaluate(FEATURE_REGISTRY_PATH, '+      - name: BRAND_NEW_FLAG'), {
        behaviour: true,
        cleanup: false,
    });
});

test('a path owned by a pending feature is a behaviour change on touch alone', () => {
    assert.deepEqual(evaluate('src/Core/Checkout/DocumentV2/Generation/DocumentGenerator.php', '+        return $x;'), {
        behaviour: true,
        cleanup: false,
    });
});

test('an admin file named after the feature counts too', () => {
    assert.deepEqual(
        evaluate('src/Administration/Resources/app/administration/src/core/service/api/documentV2.api.service.ts', '+  const x = 1;'),
        { behaviour: true, cleanup: false },
    );
});

test('a sibling of an owned path does not count', () => {
    assert.deepEqual(evaluate('src/Core/Checkout/Document/Renderer/InvoiceRenderer.php', '+        return $x;'), {
        behaviour: false,
        cleanup: false,
    });
});

test('a deprecation for the target major is cleanup, not behaviour', () => {
    assert.deepEqual(evaluate('src/Core/Framework/Feature.php', '+     * @deprecated tag:v6.8.0 - Will be removed'), {
        behaviour: false,
        cleanup: true,
    });
});

test('a deprecation for a later major is neither', () => {
    assert.deepEqual(evaluate('src/Core/Framework/Feature.php', '+     * @deprecated tag:v6.9.0 - Will be removed'), {
        behaviour: false,
        cleanup: false,
    });
});

test('a removed deprecation still counts as cleanup', () => {
    assert.deepEqual(evaluate('src/Core/Framework/Feature.php', '-     * @deprecated tag:v6.8.0 - Will be removed'), {
        behaviour: false,
        cleanup: true,
    });
});

test('one pull request can earn both labels', () => {
    const diff =
        diffFor('UPGRADE-6.8.md', '+## The sorter changes') +
        '\n' +
        diffFor('src/Core/Content/Product/Sorter.php', '+     * @deprecated tag:v6.8.0 - use sortUsingLocaleCode');
    const evaluated = evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS, isNextMajor: true });
    assert.deepEqual(evaluated, { behaviour: true, cleanup: true });
    assert.deepEqual(labelNamesFor('6.8', evaluated), ['major/6.8', 'major/6.8-cleanup']);
});

test('markers inside .github tooling are ignored', () => {
    for (const path of ['.github/bin/js/major-label.test.ts', '.github/workflows/php.yml']) {
        assert.deepEqual(evaluate(path, "+    #[ReturnTypeNarrowing(version: 'v6.8.0')]"), {
            behaviour: false,
            cleanup: false,
        });
    }
});

test('a mixed diff still matches through the non-excluded file', () => {
    const diff =
        diffFor('.github/workflows/php.yml', '+  # v6.8.0.0 gate') +
        '\n' +
        diffFor('src/Core/Cart.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.deepEqual(evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS, isNextMajor: true }), {
        behaviour: true,
        cleanup: false,
    });
});

test('unrelated changes match nothing', () => {
    assert.deepEqual(evaluate('src/Core/Cart.php', '+        $this->calculator->calculate($cart);'), {
        behaviour: false,
        cleanup: false,
    });
});

test('regex metacharacters in a version or flag name are matched literally', () => {
    const flags = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: FLAG(A|B)
            default: false
            major: v6.8.0.0
`);
    // A partial escape would compile `6.8` into a dot wildcard and `FLAG(A|B)` into a group.
    const evaluated = evaluateMajorLabels({
        diff: diffFor('src/Core/Cart.php', "+        Feature::isActive('FLAGA');"),
        flags,
        targetMajor: '6.8',
        majorPaths: [],
        isNextMajor: true,
    });
    assert.deepEqual(evaluated, { behaviour: false, cleanup: false });

    assert.deepEqual(
        evaluateMajorLabels({
            diff: diffFor('src/Core/Cart.php', "+        Feature::isActive('FLAG(A|B)');"),
            flags,
            targetMajor: '6.8',
            majorPaths: [],
            isNextMajor: true,
        }),
        { behaviour: true, cleanup: false },
    );
});

test('a version dot is not a wildcard', () => {
    assert.deepEqual(evaluate('src/Core/Framework/Feature.php', '+     * @deprecated tag:v6x8.0 - nope'), {
        behaviour: false,
        cleanup: false,
    });
});

test('an empty diff matches nothing', () => {
    assert.deepEqual(evaluateMajorLabels({ diff: '', flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS }), {
        behaviour: false,
        cleanup: false,
    });
});

test('labelNamesFor emits only the earned labels', () => {
    assert.deepEqual(labelNamesFor('6.8', { behaviour: true, cleanup: false }), ['major/6.8']);
    assert.deepEqual(labelNamesFor('6.8', { behaviour: false, cleanup: true }), ['major/6.8-cleanup']);
    assert.deepEqual(labelNamesFor('6.8', { behaviour: false, cleanup: false }), []);
});

const detectionContext = (action: string, eventName = 'pull_request_target', base = 'trunk') => ({
    eventName,
    payload: { action, pull_request: { base: { ref: base } } },
});

test('shouldDetect accepts the triggering pull request actions', () => {
    for (const action of ['opened', 'reopened', 'synchronize', 'ready_for_review']) {
        assert.equal(shouldDetect(detectionContext(action)), true);
    }
});

test('shouldDetect rejects other actions and events', () => {
    assert.equal(shouldDetect(detectionContext('labeled')), false);
    assert.equal(shouldDetect(detectionContext('closed')), false);
    assert.equal(shouldDetect(detectionContext('opened', 'pull_request')), false);
    assert.equal(shouldDetect(detectionContext('opened', 'issues')), false);
});

test('shouldDetect rejects pull requests into a release branch', () => {
    assert.equal(shouldDetect(detectionContext('opened', 'pull_request_target', '6.7.14.x')), false);
    assert.equal(shouldDetect(detectionContext('synchronize', 'pull_request_target', '6.6.x')), false);
});

test('shouldDetect accepts a stacked pull request, which reaches trunk without a new trigger', () => {
    assert.equal(shouldDetect(detectionContext('opened', 'pull_request_target', 'codex/major-feature-inheritance')), true);
});

test('isReleaseBranch knows the maintenance and security branch names', () => {
    for (const ref of ['6.6.x', '6.7.x', '6.7.14.x', '6.7.14.1']) {
        assert.equal(isReleaseBranch(ref), true, ref);
    }
    for (const ref of ['trunk', 'feat/intra-eu-tax-calculation', 'next-6.8', 'release/6.7.x', '6.x-cleanup']) {
        assert.equal(isReleaseBranch(ref), false, ref);
    }
});

test('withoutRemovedLabels keeps a label off once anyone has removed it', () => {
    const events = [
        { event: 'labeled', label: { name: 'major/6.8' } },
        { event: 'unlabeled', label: { name: 'major/6.8' } },
        { event: 'labeled', label: { name: 'domain/checkout' } },
        { event: 'unlabeled', label: { name: 'domain/checkout' } },
        { event: 'review_requested' },
    ];
    assert.deepEqual(withoutRemovedLabels(['major/6.8', 'major/6.8-cleanup'], events), ['major/6.8-cleanup']);
    assert.deepEqual(withoutRemovedLabels(['major/6.8'], []), ['major/6.8']);
});

test('missingLabels drops labels the pull request already carries', () => {
    const context = {
        eventName: 'pull_request_target',
        repo: { owner: 'shopware', repo: 'shopware' },
        payload: {
            action: 'synchronize',
            pull_request: { number: 1, base: { ref: 'trunk' }, labels: [{ name: 'major/6.8' }, { name: 'domain/checkout' }] },
        },
    };
    assert.deepEqual(missingLabels(context, ['major/6.8', 'major/6.8-cleanup']), ['major/6.8-cleanup']);
    assert.deepEqual(missingLabels(context, ['major/6.8']), []);
});

const TWO_MAJORS = parseFeatureRegistry(`shopware:
  feature:
    flags:
      - name: v6.7.0.0
        default: true
      - name: v6.8.0.0
        default: false
      - name: v6.9.0.0
        default: false
      - name: WEBHOOKS_REWORK
        default: false
        major: v6.8.0.0
`);

const labelsFor = (path: string, hunk: string) =>
    labelsForDiff({ diff: diffFor(path, hunk), flags: TWO_MAJORS, majorPaths: PATHS });

test('two in-flight majors: a 6.9 deprecation labels 6.9 alone', () => {
    assert.deepEqual(labelsFor('src/Core/Framework/Feature.php', '+ * @deprecated tag:v6.9.0 - gone in 6.9'), [
        'major/6.9-cleanup',
    ]);
});

test('two in-flight majors: a 6.8 deprecation still labels 6.8 alone', () => {
    assert.deepEqual(labelsFor('src/Core/Framework/Feature.php', '+ * @deprecated tag:v6.8.0 - gone in 6.8'), [
        'major/6.8-cleanup',
    ]);
});

test('two in-flight majors: each UPGRADE file labels its own major', () => {
    assert.deepEqual(labelsFor('UPGRADE-6.9.md', '+## Later'), ['major/6.9']);
    assert.deepEqual(labelsFor('UPGRADE-6.8.md', '+## Sooner'), ['major/6.8']);
});

test('two in-flight majors: a version flag labels the major it names', () => {
    assert.deepEqual(labelsFor('src/Core/Cart.php', "+        if (Feature::isActive('v6.9.0.0')) {"), ['major/6.9']);
});

test('an unversioned flag belongs to the nearest major, not to every one', () => {
    assert.deepEqual(labelsFor('src/Core/Cart.php', "+        if (Feature::isActive('WEBHOOKS_REWORK')) {"), [
        'major/6.8',
    ]);
});

test('a path map hit belongs to the nearest major', () => {
    assert.deepEqual(labelsFor('src/Core/Checkout/DocumentV2/Generation/DocumentGenerator.php', '+        return $x;'), [
        'major/6.8',
    ]);
});

test('a registry edit belongs to the nearest major', () => {
    assert.deepEqual(labelsFor(FEATURE_REGISTRY_PATH, '+      - name: BRAND_NEW_FLAG'), ['major/6.8']);
});

test('one pull request can earn labels for both majors', () => {
    const diff =
        diffFor('UPGRADE-6.8.md', '+## Sooner') +
        '\n' +
        diffFor('src/Core/Framework/Feature.php', '+ * @deprecated tag:v6.9.0 - gone in 6.9');
    assert.deepEqual(labelsForDiff({ diff, flags: TWO_MAJORS, majorPaths: PATHS }), [
        'major/6.8',
        'major/6.9-cleanup',
    ]);
});

test('labelsForDiff emits nothing when no major is in flight', () => {
    const shipped = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: v6.8.0.0
            default: true
`);
    assert.deepEqual(
        labelsForDiff({ diff: diffFor('UPGRADE-6.8.md', '+## Anything'), flags: shipped, majorPaths: PATHS }),
        [],
    );
});

const tooLarge = Object.assign(new Error('Sorry, the diff exceeded the maximum number of files (300).'), {
    status: 406,
    response: { data: { errors: [{ resource: 'PullRequest', field: 'diff', code: 'too_large' }] } },
});

test('isDiffTooLarge recognises only the too_large refusal', () => {
    assert.equal(isDiffTooLarge(tooLarge), true);
    assert.equal(isDiffTooLarge(Object.assign(new Error('Not Acceptable'), { status: 406 })), false);
    assert.equal(isDiffTooLarge(Object.assign(new Error('other side closed'), { status: 500 })), false);
    assert.equal(isDiffTooLarge(undefined), false);
});

test('diffFromFiles rebuilds a diff the detection reads like the native one', () => {
    const diff = diffFromFiles([
        { filename: 'src/Core/Framework/Feature.php', patch: '@@ -1,1 +1,1 @@\n+     * @deprecated tag:v6.8.0 - Will be removed' },
        { filename: 'src/Core/Checkout/DocumentV2/Huge.php' },
    ]);
    assert.deepEqual(evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS, isNextMajor: true }), {
        behaviour: true,
        cleanup: true,
    });
});

const detect = (github: object, labels: Array<{ name: string }> = []) => {
    const warnings: string[] = [];
    const files: Record<string, string> = { [FEATURE_REGISTRY_PATH]: REGISTRY, [MAJOR_PATHS_PATH]: PATH_MAP };
    const run = detectMajorLabels(
        {
            github: github as Parameters<typeof detectMajorLabels>[0]['github'],
            core: { info: () => {}, warning: (message) => warnings.push(message) },
            context: {
                eventName: 'pull_request_target',
                repo: { owner: 'shopware', repo: 'shopware' },
                payload: {
                    action: 'synchronize',
                    pull_request: { number: 1, base: { ref: 'trunk' }, labels },
                },
            },
        },
        (path) => files[path],
    );

    return { run, warnings };
};

test('a diff too large for the diff format is read through the files endpoint', async () => {
    const listFiles = async () => ({ data: [] });
    const listEvents = async () => ({ data: [] });
    const { run, warnings } = detect({
        paginate: async (route: unknown, options: object) => {
            if (route === listEvents) {
                return [];
            }
            assert.equal(route, listFiles);
            assert.deepEqual(options, { owner: 'shopware', repo: 'shopware', pull_number: 1, per_page: 100 });

            return [
                { filename: 'UPGRADE-6.8.md', patch: '@@ -1,1 +1,1 @@\n+## Something breaks' },
                { filename: 'src/Core/Huge.php' },
            ];
        },
        rest: {
            pulls: {
                get: async () => {
                    throw tooLarge;
                },
                listFiles,
            },
            issues: { listEvents },
        },
    });

    assert.deepEqual(await run, ['major/6.8']);
    assert.deepEqual(warnings, [
        'diff too large, read 2 file(s) through the files endpoint instead; 1 without a patch are matched by path only',
    ]);
});

test('any other diff failure still fails the run', async () => {
    const outage = Object.assign(new Error('other side closed'), { status: 500 });
    const { run } = detect({
        paginate: async () => assert.fail('must not fall back on an outage'),
        rest: {
            pulls: {
                get: async () => {
                    throw outage;
                },
                listFiles: async () => ({ data: [] }),
            },
        },
    });

    await assert.rejects(run, outage);
});

const upgradeDiff = diffFor('UPGRADE-6.8.md', '+## Something breaks');

test('a label someone removed is not added back on the next push', async () => {
    const listEvents = async () => ({ data: [] });
    const { run } = detect({
        paginate: async (route: unknown, options: object) => {
            assert.equal(route, listEvents);
            assert.deepEqual(options, { owner: 'shopware', repo: 'shopware', issue_number: 1, per_page: 100 });

            return [
                { event: 'labeled', label: { name: 'major/6.8' } },
                { event: 'unlabeled', label: { name: 'major/6.8' } },
            ];
        },
        rest: { pulls: { get: async () => ({ data: upgradeDiff }) }, issues: { listEvents } },
    });

    assert.deepEqual(await run, []);
});

test('the event history is only read when a label is missing', async () => {
    const { run } = detect(
        {
            paginate: async () => assert.fail('no label is missing, so no history is needed'),
            rest: { pulls: { get: async () => ({ data: upgradeDiff }) }, issues: { listEvents: async () => ({ data: [] }) } },
        },
        [{ name: 'major/6.8' }],
    );

    assert.deepEqual(await run, []);
});
