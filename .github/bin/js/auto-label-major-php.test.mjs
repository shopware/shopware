import assert from 'node:assert/strict';
import { test } from 'node:test';
import { FEATURE_REGISTRY_PATH, hasMajorMarkers, parseMajorFlags, shouldDetect } from './auto-label-major-php.mjs';

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
    const diff = "+        if (Feature::isActive('v6.8.0.0')) {\n";
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('quoted major flag in a removed line matches', () => {
    const diff = "-        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => null);\n";
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('deprecation tag annotation matches', () => {
    const diff = '+     * @deprecated tag:v6.9.0 - Will be removed\n';
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('registry file edits match regardless of line content', () => {
    const diff = `--- a/${FEATURE_REGISTRY_PATH}\n+++ b/${FEATURE_REGISTRY_PATH}\n+            - name: NEW_FLAG\n`;
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('non-major flag usage does not match', () => {
    const diff = "+        if (Feature::isActive('TELEMETRY_METRICS')) {\n";
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('unrelated changes do not match', () => {
    const diff = "+        \\$cmsPage = \\$this->cmsPageLoader->load(\\$request, \\$criteria, \\$context)->getEntities()->first();\n";
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('empty diff does not match', () => {
    assert.equal(hasMajorMarkers('', parseMajorFlags(REGISTRY)), false);
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
