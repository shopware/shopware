import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { classifyPlaywrightReport } from './report-classifier.ts';
import type { PlaywrightReport } from './report-classifier.ts';

const plan = { version: '6.6.0.0' };

/**
 * Builds a minimal Playwright JSON report with the given stats and a single
 * failed (or timedOut) error node nested the way runner output arrives.
 */
function reportWith(
  { expected = 0, unexpected = 0, skipped = 0 }: { expected?: number; unexpected?: number; skipped?: number },
  message?: string,
  status: string = 'failed',
): PlaywrightReport {
  const report: PlaywrightReport = { stats: { expected, unexpected, skipped } };
  if (message !== undefined) {
    report.suites = [
      {
        specs: [
          {
            tests: [
              {
                results: [{ status, error: { message } }],
              },
            ],
          },
        ],
      },
    ];
  }
  return report;
}

test('all tests passing maps to not_reproduced', () => {
  const out = classifyPlaywrightReport({ plan, report: reportWith({ expected: 3 }) });
  assert.equal(out.status, 'not_reproduced');
  assert.equal(out.actual, '3 passing');
  assert.match(out.reporter, /all 3 test\(s\) passed/);
  assert.equal(out.reason, null);
});

test('a failing value assertion maps to reproduced', () => {
  const message = [
    'Error: expect(locator).toHaveText(expected)',
    'Expected string: "5"',
    'Received string: "0"',
    'locator resolved to <div>0</div>',
  ].join('\n');
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'reproduced');
  assert.equal(out.actual, '1 failing');
  assert.match(out.reporter, /expect\(locator\)\.toHaveText/);
});

test('a timed-out "waiting for locator to be visible" maps to inconclusive precondition failed', () => {
  const message = [
    'Error: locator.click: Timeout 30000ms exceeded.',
    "waiting for locator('#missing') to be visible",
  ].join('\n');
  const out = classifyPlaywrightReport({
    plan,
    report: reportWith({ unexpected: 1 }, message, 'timedOut'),
  });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, '1 failing (precondition failed)');
  assert.match(out.reason!, /an element the spec required never rendered/);
});

test('a PRECONDITION_NOT_FOUND marker maps to inconclusive precondition failed', () => {
  const message = 'Error: PRECONDITION_NOT_FOUND: settings toggle absent';
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, `precondition failed on ${plan.version}`);
  assert.match(out.reason!, /a required precondition element is absent on 6\.6\.0\.0/);
});

test('a navigation failure maps to inconclusive could-not-load', () => {
  const message = 'Error: page.goto: net::ERR_CONNECTION_REFUSED at http://localhost';
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, `could not load the page on ${plan.version}`);
  assert.match(out.reason!, /failed to load the target page/);
});

test('a strict-mode violation maps to inconclusive ambiguous locator', () => {
  const message = "Error: strict mode violation: locator('button') resolved to 3 elements";
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, '1 failing (ambiguous locator)');
  assert.match(out.reason!, /strict-mode violation/);
});

test('an element-never-resolved failure maps to inconclusive precondition failed', () => {
  const message = 'Error: locator.textContent: element(s) not found for locator';
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, '1 failing (precondition failed)');
  assert.match(out.reason!, /never rendered/);
});

test('an "element(s) not found" that DID resolve is not downgraded to precondition', () => {
  // When Playwright logged "locator resolved to", the element existed; a value assertion on
  // it is a real symptom rather than a missing-precondition surface.
  const message = [
    'Error: expect(locator).toBeVisible()',
    'locator resolved to <div hidden>',
    'element(s) not found',
  ].join('\n');
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'reproduced');
});

test('an unrecognised failure falls back to inconclusive precondition failed', () => {
  const message = 'Error: Target page, context or browser has been closed';
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, '1 failing (precondition failed)');
  assert.match(out.reason!, /locator\/timeout error, not an assertion/);
});

test('no tests executed maps to inconclusive', () => {
  const out = classifyPlaywrightReport({ plan, report: reportWith({}) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, 'no tests ran');
  assert.match(out.reason!, /ran no tests/);
});

test('a self-skipped spec (skipped, none expected) maps to inconclusive precondition-not-met', () => {
  const out = classifyPlaywrightReport({ plan, report: reportWith({ skipped: 1 }) });
  assert.equal(out.status, 'inconclusive');
  assert.equal(out.actual, '1 skipped');
  assert.match(out.reason!, /precondition is not met on 6\.6\.0\.0/);
});

test('an expected pass alongside a skip is still healthy (not_reproduced)', () => {
  const out = classifyPlaywrightReport({ plan, report: reportWith({ expected: 1, skipped: 1 }) });
  assert.equal(out.status, 'not_reproduced');
  assert.equal(out.actual, '1 passing');
});

test('ANSI SGR escape codes are stripped from the reporter output', () => {
  const esc = '';
  const message = [
    `${esc}[31mError: expect(locator).toHaveText(expected)${esc}[39m`,
    `${esc}[32mExpected string: "5"${esc}[39m`,
    `${esc}[31mReceived string: "0"${esc}[39m`,
    'locator resolved to <div>0</div>',
  ].join('\n');
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.status, 'reproduced');
  assert.doesNotMatch(out.reporter, new RegExp(esc));
  assert.match(out.reporter, /Error: expect\(locator\)\.toHaveText/);
});

test('the reporter preview is capped at 1200 characters', () => {
  const message = `Error: expect(locator).toBeVisible()\nlocator resolved to <div>\n${'x'.repeat(4000)}`;
  const out = classifyPlaywrightReport({ plan, report: reportWith({ unexpected: 1 }, message) });
  assert.equal(out.reporter.length, 1200);
});

test('collects errors from a timedOut node the same as a failed node', () => {
  const message = 'Error: PRECONDITION_NOT_FOUND: nested timeout';
  const out = classifyPlaywrightReport({
    plan,
    report: reportWith({ unexpected: 1 }, message, 'timedOut'),
  });
  assert.equal(out.status, 'inconclusive');
  assert.match(out.reason!, /precondition element is absent/);
});

test('a missing report reads pw-stderr.txt and maps to blocked', () => {
  const cwd = process.cwd();
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pw-report-'));
  fs.writeFileSync(
    path.join(dir, 'pw-stderr.txt'),
    'some earlier noise\nError: Cannot find module @playwright/test\n',
  );
  try {
    process.chdir(dir);
    const out = classifyPlaywrightReport({ plan, report: null });
    assert.equal(out.status, 'blocked');
    assert.equal(out.actual, null);
    assert.equal(out.reporter, 'runner error: Error: Cannot find module @playwright/test');
    assert.match(out.reason!, /no parseable report/);
  } finally {
    process.chdir(cwd);
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

test('a missing report with an empty stderr reports "unknown"', () => {
  const cwd = process.cwd();
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pw-report-'));
  fs.writeFileSync(path.join(dir, 'pw-stderr.txt'), '');
  try {
    process.chdir(dir);
    const out = classifyPlaywrightReport({ plan, report: null });
    assert.equal(out.status, 'blocked');
    assert.equal(out.reporter, 'runner error: unknown');
  } finally {
    process.chdir(cwd);
    fs.rmSync(dir, { recursive: true, force: true });
  }
});
