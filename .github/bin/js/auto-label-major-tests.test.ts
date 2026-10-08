import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    FEATURE_REGISTRY_PATH,
    detectMajorTestArms,
    hasAcceptanceTestChanges,
    hasMajorJsMarkers,
    hasMajorMarkers,
    labelsForMajorTestArms,
    parseMajorFlags,
    shouldDetect,
} from './auto-label-major-tests.ts';


type TestContext = {
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
            labels: Array<{ name: string }>;
        };
    };
};

const diffFor = (path: string, hunk: string): string => `diff --git a/${path} b/${path}
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
              toggleable: false
            - name: WEBHOOKS_REWORK
              default: false
              major: v6.8.0.0
              toggleable: true
            - name: TELEMETRY_METRICS
              default: false
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

test('major markers in Administration source or tests enable the major-js arm', () => {
    const sourceDiff = diffFor(
        'src/Administration/Resources/app/administration/src/app/component/example/index.ts',
        "+        return Shopware.Feature.isActive('v6.8.0.0');",
    );
    const testDiff = diffFor(
        'src/Administration/Resources/app/administration/test/_setup/example.spec.ts',
        "+        it.deprecated('v6.8.0.0')('keeps the legacy path', () => {});",
    );

    assert.equal(hasMajorJsMarkers(sourceDiff, parseMajorFlags(REGISTRY)), true);
    assert.equal(hasMajorJsMarkers(testDiff, parseMajorFlags(REGISTRY)), true);
});

test('major markers outside Administration source and tests do not enable the major-js arm', () => {
    const phpDiff = diffFor('src/Core/Framework/Feature.php', "+        Feature::isActive('v6.8.0.0');");
    const documentationDiff = diffFor(
        'src/Administration/Resources/app/administration/technical-docs/guide.md',
        '+ v6.8.0.0',
    );

    assert.equal(hasMajorJsMarkers(phpDiff, parseMajorFlags(REGISTRY)), false);
    assert.equal(hasMajorJsMarkers(documentationDiff, parseMajorFlags(REGISTRY)), false);
});

test('feature registry edits enable the major-js arm', () => {
    const diff = diffFor(FEATURE_REGISTRY_PATH, '+            - name: NEW_FLAG');

    assert.equal(hasMajorJsMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('labelsForMajorTestArms adds only missing relevant labels', () => {
    assert.deepEqual(labelsForMajorTestArms({ php: true, js: true, acceptance: false }), ['major-php', 'major-js']);
    assert.deepEqual(labelsForMajorTestArms({ php: true, js: true, acceptance: true }, [{ name: 'major-php' }]), ['major-js', 'major-acceptance']);
    assert.deepEqual(labelsForMajorTestArms({ php: false, js: true, acceptance: true }, [{ name: 'major-js' }, { name: 'major-acceptance' }]), []);
    assert.deepEqual(labelsForMajorTestArms({ php: false, js: false, acceptance: true }), ['major-acceptance']);
});

test('acceptance specs enable major ATS without feature-flag markers', () => {
    for (const path of ['tests/acceptance/tests/Example.spec.ts', 'tests/acceptance/tests/Checkout/Example.spec.ts']) {
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '+await button.click();')), true);
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '-await button.click();')), true);
    }
});

test('acceptance helpers, dependencies, docs and other specs do not enable major ATS', () => {
    for (const path of [
        'tests/acceptance/tasks/Example.spec.ts',
        'tests/acceptance/fixtures/Example.ts',
        'tests/acceptance/package.json',
        'tests/acceptance/tests/README.md',
        'tests/acceptance/tests/Example.ts',
        'src/Administration/Resources/app/administration/src/Example.spec.ts',
    ]) {
        assert.equal(hasAcceptanceTestChanges(diffFor(path, '+example')), false, path);
    }
    assert.equal(hasAcceptanceTestChanges(''), false);
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
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.ts', "+    const diff = \"Feature::isActive('v6.8.0.0')\";");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('registry path mentioned inside a .github file does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.ts', `+export const FEATURE_REGISTRY_PATH = '${FEATURE_REGISTRY_PATH}';`);
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('mixed diff matches through the non-excluded file only', () => {
    const diff = diffFor('.github/workflows/php.yml', "+  # v6.8.0.0 gate") + '\n' + diffFor('src/Core/CustomCartProcessor.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('added BC-change attribute usage matches through its version string', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('removed BC-change attribute usage matches through its version string', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "-    #[BecomesFinal(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('version-free attributes do not match', () => {
    const diff = diffFor('src/Core/Framework/Context.php', "+    #[Route(path: '/api/test', name: 'api.test')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('BC-change attribute in .github tooling does not match', () => {
    const diff = diffFor('.github/bin/js/auto-label-major-php.test.ts', "+    #[ReturnTypeNarrowing(version: 'v6.8.0')]");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

test('markdown never matches: release headings, upgrade notes and ADRs quote versions and flags', () => {
    const changelog = diffFor('CHANGELOG.md', '+## 6.6.10.21');
    const upgradeNotes = diffFor('UPGRADE-6.8.md', '+Tools return the MCP result format only behind the `v6.8.0.0` flag.');
    const adr = diffFor('adr/2026-09-24-mcp-tool-result-envelope.md', "+3. **Spec format only (6.8.0).** Behind the 'v6.8.0.0' flag.");
    const upperCase = diffFor('docs/README.MD', "+Feature::isActive('v6.8.0.0')");

    for (const diff of [changelog, upgradeNotes, adr, upperCase]) {
        assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), false);
    }
});

test('markdown next to a code change does not hide the code change', () => {
    const diff = diffFor('adr/2026-09-24-mcp-tool-result-envelope.md', '+Behind the `v6.8.0.0` flag.') + '\n' + diffFor('src/Core/Framework/Mcp/McpToolResponse.php', "+        if (Feature::isActive('v6.8.0.0')) {");
    assert.equal(hasMajorMarkers(diff, parseMajorFlags(REGISTRY)), true);
});

test('markdown inside Administration source does not enable the major-js arm', () => {
    const diff = diffFor('src/Administration/Resources/app/administration/src/app/component/example/README.md', "+Shopware.Feature.isActive('v6.8.0.0')");
    assert.equal(hasMajorJsMarkers(diff, parseMajorFlags(REGISTRY)), false);
});

const baseContext = (overrides: Partial<TestContext> = {}): TestContext => ({
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

test('shouldDetect permits detection until all three per-arm labels are present', () => {
    const phpOnly = baseContext();
    phpOnly.payload.pull_request.labels = [{ name: 'major-php' }];
    assert.equal(shouldDetect(phpOnly), true);

    const bothArms = baseContext();
    bothArms.payload.pull_request.labels = [{ name: 'major-php' }, { name: 'major-js' }];
    assert.equal(shouldDetect(bothArms), true);

    bothArms.payload.pull_request.labels.push({ name: 'major-acceptance' });
    assert.equal(shouldDetect(bothArms), false);

    const umbrella = baseContext();
    umbrella.payload.pull_request.labels = [{ name: 'major-tests' }];
    assert.equal(shouldDetect(umbrella), false);
});

test('detection selects only the acceptance arm for a spec change after PHP and JS were labeled', async () => {
    const context = baseContext();
    context.payload.pull_request.labels = [{ name: 'major-php' }, { name: 'major-js' }];
    const arms = await detectMajorTestArms({
        context: {
            ...context,
            payload: {
                ...context.payload,
                pull_request: {
                    ...context.payload.pull_request,
                    number: 123,
                    head: { ...context.payload.pull_request.head, sha: 'head-sha' },
                },
            },
        },
        core: { info() {} },
        github: {
            rest: {
                repos: { async getContent() { return { data: REGISTRY }; } },
                pulls: { async get() { return { data: diffFor('tests/acceptance/tests/Example.spec.ts', '+await button.click();') }; } },
                issues: { async addLabels() {} },
            },
        },
    });
    assert.deepEqual(arms, { php: false, js: false, acceptance: true });
    assert.deepEqual(labelsForMajorTestArms(arms, context.payload.pull_request.labels), ['major-acceptance']);
});
