import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    FEATURE_REGISTRY_PATH,
    evaluateMajorLabels,
    globToRegExp,
    labelNamesFor,
    missingLabels,
    parseFeatureRegistry,
    parseMajorPaths,
    pendingMajorFlags,
    resolveTargetMajor,
    shouldDetect,
} from './major-label.ts';

const REGISTRY = `shopware:
  feature:
    flags:
      - name: v6.7.0.0
        default: true
        major: true
        toggleable: false
      - name: v6.8.0.0
        default: false
        major: true
        toggleable: false
      - name: WEBHOOKS_REWORK
        default: false
        major: true
        toggleable: true
      - name: TELEMETRY_METRICS
        default: false
        major: false
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
    evaluateMajorLabels({ diff: diffFor(path, hunk), flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS });

test('parseFeatureRegistry reads name, major and default per flag', () => {
    assert.deepEqual(FLAGS, [
        { name: 'v6.7.0.0', major: true, default: true },
        { name: 'v6.8.0.0', major: true, default: false },
        { name: 'WEBHOOKS_REWORK', major: true, default: false },
        { name: 'TELEMETRY_METRICS', major: false, default: false },
    ]);
});

test('pendingMajorFlags excludes flags that already default to true', () => {
    assert.deepEqual(pendingMajorFlags(FLAGS), ['v6.8.0.0', 'WEBHOOKS_REWORK']);
});

test('resolveTargetMajor derives the version from the pending major flag', () => {
    assert.equal(resolveTargetMajor(FLAGS), '6.8');
});

test('resolveTargetMajor picks the lowest pending major', () => {
    const flags = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: v6.9.0.0
            default: false
            major: true
          - name: v6.8.0.0
            default: false
            major: true
`);
    assert.equal(resolveTargetMajor(flags), '6.8');
});

test('resolveTargetMajor returns null once every major flag has flipped', () => {
    const flags = parseFeatureRegistry(`shopware:
      feature:
        flags:
          - name: v6.8.0.0
            default: true
            major: true
`);
    assert.equal(resolveTargetMajor(flags), null);
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

test('a stabilising experimental annotation is a behaviour change', () => {
    assert.deepEqual(
        evaluate('src/Core/Checkout/Cart/Cart.php', '+ * @experimental stableVersion:v6.8.0 feature:CACHE_REWORK'),
        { behaviour: true, cleanup: false },
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
    const evaluated = evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS });
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
    assert.deepEqual(evaluateMajorLabels({ diff, flags: FLAGS, targetMajor: '6.8', majorPaths: PATHS }), {
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
            major: true
`);
    // A partial escape would compile `6.8` into a dot wildcard and `FLAG(A|B)` into a group.
    const evaluated = evaluateMajorLabels({
        diff: diffFor('src/Core/Cart.php', "+        Feature::isActive('FLAGA');"),
        flags,
        targetMajor: '6.8',
        majorPaths: [],
    });
    assert.deepEqual(evaluated, { behaviour: false, cleanup: false });

    assert.deepEqual(
        evaluateMajorLabels({
            diff: diffFor('src/Core/Cart.php', "+        Feature::isActive('FLAG(A|B)');"),
            flags,
            targetMajor: '6.8',
            majorPaths: [],
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

const detectionContext = (action: string, eventName = 'pull_request_target') => ({ eventName, payload: { action } });

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

test('missingLabels drops labels the pull request already carries', () => {
    const context = {
        eventName: 'pull_request_target',
        repo: { owner: 'shopware', repo: 'shopware' },
        payload: {
            action: 'synchronize',
            pull_request: { number: 1, labels: [{ name: 'major/6.8' }, { name: 'domain/checkout' }] },
        },
    };
    assert.deepEqual(missingLabels(context, ['major/6.8', 'major/6.8-cleanup']), ['major/6.8-cleanup']);
    assert.deepEqual(missingLabels(context, ['major/6.8']), []);
});
