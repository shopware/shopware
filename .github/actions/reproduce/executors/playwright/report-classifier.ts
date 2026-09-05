import fs from 'node:fs';
import type { LegStatus } from '../../types.ts';

export interface PlaywrightReport {
  stats?: { expected?: number; unexpected?: number; skipped?: number };
  suites?: unknown[];
  [key: string]: unknown;
}

interface PlaywrightOutcome {
  status: LegStatus;
  actual: string | null;
  reporter: string;
  reason: string | null;
}

interface ClassifyInput {
  plan: { version: string };
  report: PlaywrightReport | null;
}

interface PlaywrightNode {
  status?: string;
  error?: { message: string };
}

/**
 * Collapses Playwright stats and collected errors into one canonical executor outcome.
 *
 * A failing value assertion means the symptom reproduced; navigation, locator, and precondition
 * failures are inconclusive because the reported behavior was not actually reached.
 */
export function classifyPlaywrightReport({ plan, report }: ClassifyInput): PlaywrightOutcome {
  if (!report) {
    const stderr = fs.readFileSync('pw-stderr.txt', 'utf8').trim();
    const lastErrorLine = stderr.split('\n').pop() || 'unknown';

    return {
      status: 'blocked',
      actual: null,
      reporter: `runner error: ${lastErrorLine}`,
      reason: 'playwright produced no parseable report (env not ready?)',
    };
  }

  const expected = report.stats?.expected ?? 0;
  const unexpected = report.stats?.unexpected ?? 0;
  const skipped = report.stats?.skipped ?? 0;
  const errors = stripAnsi(collectPlaywrightErrors(report));
  const pretty = errors.slice(0, 1200);

  if (!expected && !unexpected && !skipped) {
    return outcome('inconclusive', 'no tests ran', 'no tests executed', 'playwright ran no tests');
  }
  if (unexpected > 0) {
    return classifyUnexpectedFailure({ plan, unexpected, errors, pretty });
  }
  if (skipped > 0 && !expected) {
    return outcome(
      'inconclusive',
      `${skipped} skipped`,
      'spec skipped (precondition not met on this version)',
      `the spec skipped itself (test.skip): the repro's precondition is not met on ${plan.version}`,
    );
  }

  return outcome('not_reproduced', `${expected} passing`, `all ${expected} test(s) passed (healthy)`);
}

/**
 * Separates assertion failures from setup, navigation, and locator drift failures.
 */
function classifyUnexpectedFailure({ plan, unexpected, errors, pretty }: {
  plan: { version: string };
  unexpected: number;
  errors: string;
  pretty: string;
}): PlaywrightOutcome {
  if (/PRECONDITION_NOT_FOUND/.test(errors)) {
    return outcome(
      'inconclusive',
      `precondition failed on ${plan.version}`,
      pretty,
      `could not set up the shop to the state the reproduction needs: a required precondition element is absent on ${plan.version}, so the symptom was never exercised (precondition failed)`,
    );
  }
  if (/net::ERR|ERR_CONNECTION|page\.goto|waiting for navigation|Navigation to .* failed/i.test(errors)) {
    return outcome(
      'inconclusive',
      `could not load the page on ${plan.version}`,
      pretty,
      `could not set up the environment: the spec failed to load the target page on ${plan.version}, so the symptom cannot be judged (precondition failed)`,
    );
  }
  if (/strict mode violation/i.test(errors)) {
    return outcome(
      'inconclusive',
      `${unexpected} failing (ambiguous locator)`,
      pretty,
      'the locator matched multiple elements (strict-mode violation), so the assertion did not test one specific state',
    );
  }
  // Only inconclusive when the element genuinely never resolved (missing/hidden = surface not
  // reached, could be cross-version drift). If Playwright logged "locator resolved to", the element
  // was found and a failed value assertion on it (empty/wrong text) is a real symptom → reproduced.
  const elementResolved = /locator resolved to/i.test(errors);
  if (!elementResolved && /element\(s\) not found|waiting for (?:selector|locator).*to be visible/i.test(errors)) {
    return outcome(
      'inconclusive',
      `${unexpected} failing (precondition failed)`,
      pretty,
      'could not set up the environment to the state needed to reproduce the issue: an element the spec required never rendered, so the symptom could not be observed (precondition failed)',
    );
  }
  if (isValueAssertionFailure(errors)) {
    return outcome('reproduced', `${unexpected} failing`, pretty || 'assertion failed');
  }

  return outcome(
    'inconclusive',
    `${unexpected} failing (precondition failed)`,
    pretty,
    'could not set up the environment to observe the symptom: the failure was a locator/timeout error, not an assertion on a rendered element (precondition failed)',
  );
}

function outcome(status: LegStatus, actual: string | null, reporter: string, reason: string | null = null): PlaywrightOutcome {
  return { status, actual, reporter, reason };
}

function isValueAssertionFailure(errors: string): boolean {
  return /Error: expect\(locator\)|expect\(locator\)\.|Expected:|Expected (pattern|string|value)|Received (string|value)|toBe|toHave|toContain|toEqual/.test(errors);
}

/**
 * Walks Playwright's nested report tree and collects failed test error messages.
 */
function collectPlaywrightErrors(report: PlaywrightReport): string {
  const messages: string[] = [];
  const stack: unknown[] = [report];

  while (stack.length > 0) {
    const node = stack.pop();
    if (!node) {
      continue;
    }

    if (Array.isArray(node)) {
      stack.push(...node);
    } else if (typeof node === 'object') {
      if (((node as PlaywrightNode).status === 'failed' || (node as PlaywrightNode).status === 'timedOut') && (node as PlaywrightNode).error?.message) {
        messages.push((node as PlaywrightNode).error!.message);
      }
      stack.push(...Object.values(node));
    }
  }

  return messages.join('\n\n---\n\n');
}

function stripAnsi(value: string): string {
  // eslint-disable-next-line no-control-regex -- intentional: strip ANSI SGR escapes from tool output
  return value.replace(/\u001b\[[0-9;]*m/g, '');
}
