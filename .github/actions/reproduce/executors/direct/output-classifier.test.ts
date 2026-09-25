import { test } from 'node:test';
import assert from 'node:assert/strict';
import { classifyPhpunitOutput } from './output-classifier.ts';

const FAIL_HEADER = 'PHPUnit 10.5.0\n\nF                          1 / 1 (100%)\n\nThere was 1 failure:\n\n';
const SUMMARY = '\nFAILURES!\nTests: 1, Assertions: 1, Failures: 1.\n';

test('a failure matching the symptom marker -> reproduced', () => {
  const output = `${FAIL_HEADER}1) Shopware\\Tests\\Integration\\Repro\\ReproTest::testSymptom\nFailed asserting that false is true (priceIncludesTax mismatch).\n\n/app/tests/integration/Repro/ReproTest.php:42\n${SUMMARY}`;
  const r = classifyPhpunitOutput(output, { assertion: { symptom_pattern: 'priceIncludesTax mismatch' } });
  assert.equal(r.status, 'reproduced');
});

test('token only in the (stripped) test-method header, not the failure -> inconclusive', () => {
  // Regression guard: matching the whole output would flip a failed *setup* assertion to a false
  // `reproduced` because the token appears in the "1) …::testPriceIncludesTax" header line.
  const output = `${FAIL_HEADER}1) Shopware\\Tests\\Integration\\Repro\\ReproTest::testPriceIncludesTax\nFailed asserting that two strings are equal (fixture category not found).\n\n/app/tests/integration/Repro/ReproTest.php:20\n${SUMMARY}`;
  const r = classifyPhpunitOutput(output, { assertion: { symptom_pattern: 'priceIncludesTax' } });
  assert.equal(r.status, 'inconclusive');
});

test('a failure with no symptom marker -> inconclusive (likely a setup failure)', () => {
  const output = `${FAIL_HEADER}1) ReproTest::testSymptom\nFailed asserting that null is not null.\n\n/app/x.php:10\n${SUMMARY}`;
  const r = classifyPhpunitOutput(output, { assertion: {} });
  assert.equal(r.status, 'inconclusive');
});

test('a clean PHPUnit run -> not_reproduced (healthy)', () => {
  const r = classifyPhpunitOutput('PHPUnit 10.5.0\n\n.  1 / 1 (100%)\n\nOK (1 test, 2 assertions)\n', { assertion: { symptom_pattern: 'x' } });
  assert.equal(r.status, 'not_reproduced');
});

test('output with no recognisable result -> blocked', () => {
  const r = classifyPhpunitOutput('could not connect to database\n', { assertion: { symptom_pattern: 'x' } });
  assert.equal(r.status, 'blocked');
});
