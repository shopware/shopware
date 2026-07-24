import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FEATURE_REGISTRY_PATH, hasMajorMarkers, parseBCChangeAttributes, parseMajorFlags, shouldDetect } from './auto-label-major-php.mjs';


const diffFor = (path, hunk) => `diff --git a/${path} b/${path}
index 0000000..1111111 100644
--- a/${path}
+++ b/${path}
@@ -1,1 +1,1 @@
${hunk}`;

const REGISTRY = `shopware:
    feature:
        flags:
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

test('parseMajorFlags returns only major flags', () => {
    assert.deepEqual(parseMajorFlags(REGISTRY), ['v6.8.0.0', 'WEBHOOKS_REWORK']);
});

test('quoted major flag in an added line matches', () => {
    const diff = diffFor('src/Core/Content/Cms/SalesChannel/CmsRoute.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('quoted major flag in a removed line matches', () => {
    const diff = diffFor('src/Core/Framework/Webhook/Service/WebhookManager.php', "-        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => null);");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('deprecation tag annotation matches', () => {
    const diff = diffFor('src/Core/Framework/Feature.php', '+     * @deprecated tag:v6.9.0 - Will be removed');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('registry file edits match regardless of line content', () => {
    const diff = diffFor(FEATURE_REGISTRY_PATH, '+            - name: NEW_FLAG');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('non-major flag usage does not match', () => {
    const diff = diffFor('src/Core/Telemetry/Telemetry.php', "+        if (Feature::isActive('TELEMETRY_METRICS')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('unrelated changes do not match', () => {
    const diff = diffFor('src/Core/Content/Cms/SalesChannel/CmsRoute.php', '+        $cmsPage = $this->cmsPageLoader->load($request, $criteria, $context)->getEntities()->first();');
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('empty diff does not match', () => {
    assert.equal(hasMajorMarkers('', parseMajorFlags(REGISTRY)), false);
});

test('flag usage in .github tooling does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.mjs', "+    const diff = \"Feature::isActive('v6.8.0.0')\";");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('registry path mentioned inside a .github file does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.mjs', `+export const FEATURE_REGISTRY_PATH = '${FEATURE_REGISTRY_PATH}';`);
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('mixed diff matches through the non-excluded file only', () => {
    const diff = diffFor('.github/workflows/php.yml', "+  # v6.8.0.0 gate") + '\n' + diffFor('src/Core/CustomCartProcessor.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

const ATTRIBUTE_DIR = [
    { name: 'BCChangeAttribute.php', type: 'file' },
    { name: 'BecomesFinal.php', type: 'file' },
    { name: 'ReturnTypeNarrowing.php', type: 'file' },
    { name: 'Subdir', type: 'dir' },
    { name: 'README.md', type: 'file' },
];

test('parseBCChangeAttributes keeps only php file basenames', () => {
    assert.deepEqual(parseBCChangeAttributes(ATTRIBUTE_DIR), ['BCChangeAttribute', 'BecomesFinal', 'ReturnTypeNarrowing']);
});

test('added BC-change attribute usage matches', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY), parseBCChangeAttributes(ATTRIBUTE_DIR)), true);
});

test('removed BC-change attribute usage matches', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "-    #[BecomesFinal(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY), parseBCChangeAttributes(ATTRIBUTE_DIR)), true);
});

test('qualified BC-change attribute usage matches', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[BCChange\\ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY), parseBCChangeAttributes(ATTRIBUTE_DIR)), true);
});

test('unrelated attributes do not match', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[Route(path: '/api/test', name: 'api.test')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY), parseBCChangeAttributes(ATTRIBUTE_DIR)), false);
});

test('BC-change attribute in .github tooling does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.mjs', "+    #[ReturnTypeNarrowing(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY), parseBCChangeAttributes(ATTRIBUTE_DIR)), false);
});

test('attribute usage without a known attribute list does not match', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

const baseContext = (overrides = {}) => ({
    eventName: 'pull_request',
    repo: { owner: 'shopware', repo: 'shopware' },
    payload: {
        action: 'opened',
        pull_request: {
            head: { repo: { full_name: 'shopware/shopware' } },
            labels: [],
        },
    },
    ...overrides,
});

test('shouldDetect accepts an unlabeled same-repo PR being opened', () => {
    assert.equal(shouldDetect(baseContext()), true);
});

test('shouldDetect rejects non-pull_request events', () => {
    assert.equal(shouldDetect(baseContext({ eventName: 'issues' })), false);
});

test('shouldDetect rejects other PR actions', async () => {
    const context = baseContext();
    context.payload = { ...context.payload, action: 'labeled' };
    assert.equal(shouldDetect(context), false);
});

test('shouldDetect rejects fork heads', async () => {
    const context = baseContext();
    context.payload.pull_request.head.repo.full_name = 'fork/shopware';
    assert.equal(shouldDetect(context), false);
});

test('shouldDetect rejects PRs already labeled major-php or major-tests', async () => {
    for (const name of ['major-php', 'major-tests']) {
        const context = baseContext();
        context.payload.pull_request.labels = [{ name }];
        assert.equal(shouldDetect(context), false);
    }
});
