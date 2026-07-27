import { describe, it, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

import {
  ISSUE_MARKER,
  buildIssuePayload,
  groupByDomain,
  parseJUnitReport,
  resolvePackageKey,
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

  it('builds a payload with marker, title, and capped test lists', () => {
    const tests = Array.from({ length: 45 }, (_, index) =>
      failedTest('Shopware\\Tests\\Integration\\Core\\FooTest', `testCase${String(index).padStart(2, '0')}`)
    );
    const payload = buildIssuePayload('[nightly] Nightly Major PHPUnit failures', groupByDomain(tests, '/nonexistent'), 'https://example.invalid/run/1');

    assert.equal(payload.issueTitle, '[nightly] Nightly Major PHPUnit failures');
    assert.equal(payload.issueMarker, ISSUE_MARKER);
    assert.ok(payload.issueBody.startsWith(ISSUE_MARKER));
    assert.ok(payload.issueBody.includes('Failing tests: 45'));
    assert.ok(payload.issueBody.includes('…and 5 more'));
    assert.ok(payload.issueBody.includes('`Integration\\Core\\FooTest::testCase00` — boom'));
    assert.ok(!payload.issueBody.includes('testCase44'));
    assert.ok(payload.commentBody.startsWith(ISSUE_MARKER));
    assert.ok(payload.commentBody.includes('Failing tests: 45'));
  });

  it('reports a missing-junit hint when no failures were parsed', () => {
    const payload = buildIssuePayload('[nightly] Nightly PHPUnit failures', [], 'https://example.invalid/run/1');

    assert.ok(payload.issueBody.includes('No junit reports were produced'));
  });
});
