import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import {
  buildIssuePayload,
  groupByDomain,
  jestAppRoot,
  parseJestJUnitReport,
  isNightlyBranch,
  parseJUnitReport,
  resolvePackageKey,
  scanReports,
} from './report-phpunit-nightly-failures.ts';

const JUNIT_FIXTURE = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="integration" tests="4" failures="1" errors="1">
    <testcase name="testPasses" class="Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest" file="/home/runner/work/shopware/shopware/tests/integration/Core/Checkout/CartTest.php"/>
    <testcase name="testFails" class="Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest" file="/home/runner/work/shopware/shopware/tests/integration/Core/Checkout/CartTest.php">
      <failure type="PHPUnit\\Framework\\ExpectationFailedException">Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest::testFails
Failed asserting that &quot;a&quot; is identical to &quot;b&quot;.

/home/runner/work/shopware/shopware/tests/integration/Core/Checkout/CartTest.php:42</failure>
    </testcase>
    <testcase name="testErrors with data set &quot;first&quot;" class="Shopware\\Tests\\Integration\\Storefront\\ThemeTest" file="/home/runner/work/shopware/shopware/tests/integration/Storefront/ThemeTest.php">
      <error type="RuntimeException">Shopware\\Tests\\Integration\\Storefront\\ThemeTest::testErrors with data set "first"
Table 'root_test.theme' doesn't exist</error>
    </testcase>
    <testcase name="testSkipped" class="Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest" file="/home/runner/work/shopware/shopware/tests/integration/Core/Checkout/CartTest.php">
      <skipped/>
    </testcase>
  </testsuite>
</testsuites>
`;

describe('parseJUnitReport', () => {
  it('extracts only failing and erroring testcases', () => {
    const failures = parseJUnitReport(JUNIT_FIXTURE);

    assert.equal(failures.length, 2);
    assert.deepEqual(
      failures.map((failure) => failure.testName),
      ['testFails', 'testErrors with data set "first"']
    );
  });

  it('reduces runner paths to repo-relative paths', () => {
    const failures = parseJUnitReport(JUNIT_FIXTURE);

    assert.equal(failures[0].file, 'tests/integration/Core/Checkout/CartTest.php');
  });

  it('captures the first message line after the test identifier', () => {
    const failures = parseJUnitReport(JUNIT_FIXTURE);

    assert.equal(failures[0].message, 'Failed asserting that "a" is identical to "b".');
    assert.equal(failures[1].message, "Table 'root_test.theme' doesn't exist");
  });
});

describe('resolvePackageKey', () => {
  let repoRoot: string;

  before(() => {
    repoRoot = mkdtempSync(join(tmpdir(), 'nightly-report-'));

    mkdirSync(join(repoRoot, 'tests/integration/Core/Checkout'), { recursive: true });
    writeFileSync(
      join(repoRoot, 'tests/integration/Core/Checkout/CartTest.php'),
      "<?php\n#[Package('checkout')]\nclass CartTest {}\n"
    );

    // Marker-less test whose mirrored src/ directory has a dominant package.
    mkdirSync(join(repoRoot, 'tests/integration/Core/Content/Product'), { recursive: true });
    writeFileSync(join(repoRoot, 'tests/integration/Core/Content/Product/ProductTest.php'), '<?php\nclass ProductTest {}\n');
    mkdirSync(join(repoRoot, 'src/Core/Content/Product'), { recursive: true });
    writeFileSync(join(repoRoot, 'src/Core/Content/Product/Product.php'), "<?php\n#[Package('inventory')]\nclass Product {}\n");
    writeFileSync(join(repoRoot, 'src/Core/Content/Product/ProductDefinition.php'), "<?php\n#[Package('inventory')]\nclass ProductDefinition {}\n");
    writeFileSync(join(repoRoot, 'src/Core/Content/Product/ProductException.php'), "<?php\n#[Package('framework')]\nclass ProductException {}\n");
  });

  after(() => {
    rmSync(repoRoot, { recursive: true, force: true });
  });

  it('reads the marker from the test file itself', () => {
    assert.equal(resolvePackageKey('tests/integration/Core/Checkout/CartTest.php', repoRoot), 'checkout');
  });

  it('falls back to the dominant package of the mirrored src directory', () => {
    assert.equal(resolvePackageKey('tests/integration/Core/Content/Product/ProductTest.php', repoRoot), 'inventory');
  });

  it('maps migration tests to the bundle Migration directory', () => {
    mkdirSync(join(repoRoot, 'tests/migration/Core/V6_8'), { recursive: true });
    writeFileSync(join(repoRoot, 'tests/migration/Core/V6_8/Migration1Test.php'), '<?php\nclass Migration1Test {}\n');
    mkdirSync(join(repoRoot, 'src/Core/Migration/V6_8'), { recursive: true });
    writeFileSync(join(repoRoot, 'src/Core/Migration/V6_8/Migration1.php'), "<?php\n#[Package('framework')]\nclass Migration1 {}\n");

    assert.equal(resolvePackageKey('tests/migration/Core/V6_8/Migration1Test.php', repoRoot), 'framework');
  });

  it('returns null when nothing resolves', () => {
    assert.equal(resolvePackageKey('tests/integration/Nowhere/MissingTest.php', repoRoot), null);
    assert.equal(resolvePackageKey('', repoRoot), null);
  });
});

describe('groupByDomain / buildIssuePayload', () => {
  const failedTest = (className: string, testName: string) => ({
    className,
    testName,
    file: '',
    message: 'boom',
  });

  it('groups unresolvable tests under a manual-routing bucket', () => {
    const groups = groupByDomain([failedTest('Shopware\\Tests\\Integration\\A', 'testA')], '/nonexistent');

    assert.equal(groups.length, 1);
    assert.equal(groups[0].label, 'needs manual routing');
  });

  it('builds one flat issue per group with its own marker and capped test list', () => {
    const tests = Array.from({ length: 45 }, (_, index) =>
      failedTest('Shopware\\Tests\\Integration\\Core\\FooTest', `testCase${String(index).padStart(2, '0')}`)
    );
    const payload = buildIssuePayload('[nightly] Nightly Major PHPUnit failures', groupByDomain(tests, '/nonexistent'), 'https://example.invalid/run/1');

    assert.equal(payload.issues.length, 1);
    const issue = payload.issues[0];
    assert.equal(issue.issueTitle, '[nightly] Nightly Major PHPUnit failures: needs manual routing');
    assert.equal(issue.issueMarker, '<!-- nightly-phpunit-failures:needs-manual-routing -->');
    assert.equal(issue.label, null);
    assert.ok(issue.issueBody.includes('`Integration\\Core\\FooTest::testCase00`: boom'));
    assert.ok(issue.issueBody.includes('…and 5 more'));
    assert.ok(issue.issueBody.includes('<summary>Failing tests: 45</summary>'));
    assert.ok(issue.issueBody.includes('</details>'));
    assert.ok(issue.commentBody.includes('<summary>Failing tests: 45</summary>'));
    assert.ok(!issue.issueBody.includes('testCase44'));
    assert.ok(!issue.issueBody.includes('—'));
    assert.ok(issue.issueBody.includes('deep-triage pass'));
    assert.ok(issue.commentBody.startsWith(issue.issueMarker));
  });

  it('carries the domain label for routable groups', () => {
    const group = {
      label: 'domain/checkout',
      packageKeys: new Set(['checkout']),
      tests: [failedTest('Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest', 'testFails')],
    };
    const payload = buildIssuePayload('[nightly] Nightly PHPUnit failures', [group], 'https://example.invalid/run/1');

    assert.equal(payload.issues[0].label, 'domain/checkout');
    assert.equal(payload.issues[0].issueTitle, '[nightly] Nightly PHPUnit failures: domain/checkout');
    assert.equal(payload.issues[0].issueMarker, '<!-- nightly-phpunit-failures:domain/checkout -->');
  });

  it('appends a silent-lane issue for red lanes whose reports carry no failing test', () => {
    const group = {
      label: 'domain/checkout',
      packageKeys: new Set(['checkout']),
      tests: [failedTest('Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest', 'testFails')],
    };
    const payload = buildIssuePayload('[nightly] Nightly PHPUnit failures', [group], 'https://example.invalid/run/1', [
      'junit-phpunit-blue-green-66-67',
    ]);

    assert.equal(payload.issues.length, 2);
    const silent = payload.issues[1];
    assert.equal(silent.issueTitle, '[nightly] Nightly PHPUnit failures: failed lane without test failures');
    assert.equal(silent.issueMarker, '<!-- nightly-phpunit-failures:failed-lane-without-test-failures -->');
    assert.equal(silent.label, null);
    assert.ok(silent.issueBody.includes('`junit-phpunit-blue-green-66-67`'));
    assert.ok(silent.issueBody.includes('runner-level error'));
    assert.ok(silent.commentBody.startsWith(silent.issueMarker));
  });

  it('reports only the silent lanes when every parsed report is clean', () => {
    const payload = buildIssuePayload('[nightly] Nightly PHPUnit failures', [], 'https://example.invalid/run/1', [
      'junit-phpunit-blue-green-66-67',
    ]);

    assert.equal(payload.issues.length, 1);
    assert.equal(payload.issues[0].issueTitle, '[nightly] Nightly PHPUnit failures: failed lane without test failures');
    assert.ok(!payload.issues[0].issueBody.includes('No junit reports were produced'));
  });

  it('falls back to a single no-reports issue when no failures were parsed', () => {
    const payload = buildIssuePayload('[nightly] Nightly PHPUnit failures', [], 'https://example.invalid/run/1');

    assert.equal(payload.issues.length, 1);
    const issue = payload.issues[0];
    assert.equal(issue.issueTitle, '[nightly] Nightly PHPUnit failures: no test reports');
    assert.equal(issue.issueMarker, '<!-- nightly-phpunit-failures:no-reports -->');
    assert.equal(issue.label, null);
    assert.ok(issue.issueBody.includes('No junit reports were produced'));
    assert.ok(issue.commentBody.startsWith(issue.issueMarker));
  });
});
const JEST_JUNIT_FIXTURE = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="Shopware 6 Unit Tests" tests="3" failures="1" errors="0">
  <testsuite name="src/app/component/form/sw-field" errors="0" failures="1" skipped="0" tests="3">
    <testcase classname="src/app/component/form/sw-field renders the label" name="src/app/component/form/sw-field renders the label" time="0.1">
    </testcase>
    <testcase classname="src/app/component/form/sw-field emits the change event" name="src/app/component/form/sw-field emits the change event" time="0.2">
      <failure>TypeError: wrapper.vm.emit is not a function
    at Object.&lt;anonymous&gt; (/home/runner/work/shopware/shopware/src/Administration/Resources/app/administration/src/app/component/form/sw-field.spec.js:42:5)</failure>
    </testcase>
    <testcase classname="src/app/component/form/sw-field is accessible" name="src/app/component/form/sw-field is accessible" time="0.1">
    </testcase>
  </testsuite>
</testsuites>
`;

describe('parseJestJUnitReport', () => {
  it('extracts only failing testcases and strips the suite prefix from the name', () => {
    const failures = parseJestJUnitReport(JEST_JUNIT_FIXTURE, 'src/Administration/Resources/app/administration');

    assert.equal(failures.length, 1);
    assert.equal(failures[0].className, 'src/app/component/form/sw-field');
    assert.equal(failures[0].testName, 'emits the change event');
    assert.equal(failures[0].message, 'TypeError: wrapper.vm.emit is not a function');
  });

  it('recovers the spec file from the failure stack trace', () => {
    const failures = parseJestJUnitReport(JEST_JUNIT_FIXTURE, 'src/Administration/Resources/app/administration');

    assert.equal(failures[0].file, 'src/Administration/Resources/app/administration/src/app/component/form/sw-field.spec.js');
  });

  it('falls back to the component directory when no stack trace names the spec', () => {
    const fixture = `<testsuites><testsuite name="src/app/component/form/sw-field" tests="1" failures="1">
      <testcase name="src/app/component/form/sw-field breaks" classname="src/app/component/form/sw-field breaks">
        <failure>Expected true, received false</failure>
      </testcase>
    </testsuite></testsuites>`;
    const failures = parseJestJUnitReport(fixture, 'src/Administration/Resources/app/administration');

    assert.equal(failures[0].file, 'src/Administration/Resources/app/administration/src/app/component/form/sw-field');
  });
});

describe('jestAppRoot', () => {
  it('maps jest artifacts to their app and leaves phpunit reports alone', () => {
    assert.equal(jestAppRoot('/tmp/junit-reports/jest-admin-major-results/administration.junit.xml'), 'src/Administration/Resources/app/administration');
    assert.equal(jestAppRoot('/tmp/junit-reports/jest-storefront-results/storefront.junit.xml'), 'src/Storefront/Resources/app/storefront');
    assert.equal(jestAppRoot('/tmp/junit-reports/junit-phpunit-unit/junit.xml'), null);
  });
});

describe('resolvePackageKey for jest component directories', () => {
  let repoRoot: string;

  before(() => {
    repoRoot = mkdtempSync(join(tmpdir(), 'nightly-report-jest-'));

    const component = 'src/Administration/Resources/app/administration/src/app/component/form/sw-field';
    mkdirSync(join(repoRoot, component), { recursive: true });
    writeFileSync(join(repoRoot, component, 'index.js'), '/**\n * @sw-package framework\n */\nexport default {};\n');
    writeFileSync(join(repoRoot, component, 'sw-field.spec.js'), '/**\n * @sw-package framework\n */\ndescribe(\'sw-field\', () => {});\n');
  });

  after(() => {
    rmSync(repoRoot, { recursive: true, force: true });
  });

  it('reads the dominant @sw-package marker of the component directory', () => {
    assert.equal(
      resolvePackageKey('src/Administration/Resources/app/administration/src/app/component/form/sw-field', repoRoot),
      'framework'
    );
  });

  it('routes a marker-less suite to manual routing', () => {
    const groups = groupByDomain(
      [{ className: 'eslint-rules/foo', testName: 'fails', file: '', message: 'boom' }],
      repoRoot
    );

    assert.equal(groups.length, 1);
    assert.equal(groups[0].label, 'needs manual routing');
  });
});

describe('scanReports', () => {
  let reportDir: string;

  const CLEAN_JUNIT = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="blue-green" tests="2" failures="0" errors="0">
    <testcase name="testPasses" class="Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest"/>
    <testcase name="testAlsoPasses" class="Shopware\\Tests\\Integration\\Core\\Checkout\\CartTest"/>
  </testsuite>
</testsuites>
`;

  const CLEAN_JEST = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="Shopware 6 Unit Tests" tests="1" failures="0" errors="0">
  <testsuite name="src/app/component/form/sw-field" errors="0" failures="0" tests="1">
    <testcase classname="src/app/component/form/sw-field renders" name="src/app/component/form/sw-field renders" time="0.1">
    </testcase>
  </testsuite>
</testsuites>
`;

  before(() => {
    reportDir = mkdtempSync(join(tmpdir(), 'nightly-scan-'));

    mkdirSync(join(reportDir, 'junit-phpunit-blue-green-66-67'));
    writeFileSync(join(reportDir, 'junit-phpunit-blue-green-66-67/junit.xml'), CLEAN_JUNIT);

    mkdirSync(join(reportDir, 'junit-phpunit-died-before-reporting'));

    mkdirSync(join(reportDir, 'junit-phpunit-0'));
    writeFileSync(join(reportDir, 'junit-phpunit-0/junit.xml'), JUNIT_FIXTURE);

    // the same failures again: deduplicated globally, but the lane is not silent
    mkdirSync(join(reportDir, 'junit-phpunit-1'));
    writeFileSync(join(reportDir, 'junit-phpunit-1/junit.xml'), JUNIT_FIXTURE);

    // clean jest artifacts are uploaded regardless of lane status and stay unflagged
    mkdirSync(join(reportDir, 'jest-admin-results'));
    writeFileSync(join(reportDir, 'jest-admin-results/admin.junit.xml'), CLEAN_JEST);
  });

  after(() => {
    rmSync(reportDir, { recursive: true, force: true });
  });

  it('deduplicates failures across artifacts and counts every report', () => {
    const scan = scanReports(reportDir);

    assert.equal(scan.failures.length, 2);
    assert.equal(scan.reports, 4);
  });

  it('flags only junit-phpunit lanes without a failing testcase, reportless ones included', () => {
    const scan = scanReports(reportDir);

    assert.deepEqual(scan.silentLanes, ['junit-phpunit-blue-green-66-67', 'junit-phpunit-died-before-reporting']);
  });
});

describe('isNightlyBranch', () => {
  it('accepts trunk and the maintenance branch shapes of release-gate.yml', () => {
    assert.equal(isNightlyBranch('trunk'), true);
    assert.equal(isNightlyBranch('6.6.x'), true);
    assert.equal(isNightlyBranch('6.7.11.x'), true);
  });

  it('rejects every other branch, .x-suffixed backport branches included', () => {
    assert.equal(isNightlyBranch('12753/allow-vtt-files-backport-6.6.x'), false);
    assert.equal(isNightlyBranch('fix-backport-6.6.x'), false);
    assert.equal(isNightlyBranch('6.7.0.0'), false);
    assert.equal(isNightlyBranch('trunk-test'), false);
    assert.equal(isNightlyBranch(''), false);
  });
});
