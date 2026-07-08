import fs from 'node:fs';

/**
 * Collapses Playwright stats and collected errors into one canonical executor outcome.
 *
 * A failing value assertion means the symptom reproduced; navigation, locator, and precondition
 * failures are inconclusive because the reported behavior was not actually reached.
 */
export function classifyPlaywrightReport({ plan, report }) {
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
  const short = errors.replace(/\s+/g, ' ').slice(0, 300);
  const pretty = errors.slice(0, 1200);

  if (!expected && !unexpected && !skipped) {
    return outcome('inconclusive', 'no tests ran', 'no tests executed', 'playwright ran no tests');
  }
  if (unexpected > 0) {
    return classifyUnexpectedFailure({ plan, unexpected, errors, short, pretty });
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
function classifyUnexpectedFailure({ plan, unexpected, errors, short, pretty }) {
  if (/PRECONDITION_NOT_FOUND/.test(errors)) {
    return outcome(
      'inconclusive',
      `precondition missing on ${plan.version}`,
      `precondition absent on this version (UI differs) -- ${short}`,
      `a precondition element the spec depends on is absent on ${plan.version} (likely cross-version UI drift); the symptom could not be exercised`,
    );
  }
  if (/net::ERR|ERR_CONNECTION|page\.goto|waiting for navigation|Navigation to .* failed/i.test(errors)) {
    return outcome(
      'inconclusive',
      `could not load the page on ${plan.version}`,
      `navigation/connection failure -- ${short}`,
      `the spec could not load the target page on ${plan.version}; the symptom cannot be judged`,
    );
  }
  if (/strict mode violation/i.test(errors)) {
    return outcome(
      'inconclusive',
      `${unexpected} failing (ambiguous locator)`,
      `ambiguous locator failure -- ${short}`,
      'the failure was a strict-mode locator error, not an assertion on one issue-specific state',
    );
  }
  // Only inconclusive when the element genuinely never resolved (missing/hidden = surface not
  // reached, could be cross-version drift). If Playwright logged "locator resolved to", the element
  // was found and a failed value assertion on it (empty/wrong text) is a real symptom → reproduced.
  const elementResolved = /locator resolved to/i.test(errors);
  if (!elementResolved && /element\(s\) not found|waiting for (?:selector|locator).*to be visible/i.test(errors)) {
    return outcome(
      'inconclusive',
      `${unexpected} failing (locator/precondition)`,
      `locator/precondition failure -- ${short}`,
      'the failure was a locator or missing-element error before a value assertion',
    );
  }
  if (isValueAssertionFailure(errors)) {
    return outcome('reproduced', `${unexpected} failing`, pretty || 'assertion failed');
  }

  return outcome(
    'inconclusive',
    `${unexpected} failing (non-assertion)`,
    `failure was not a value assertion (likely a missing/changed element) -- ${short}`,
    'the failure was a locator/timeout error, not an assertion on a found element',
  );
}

function outcome(status, actual, reporter, reason = null) {
  return { status, actual, reporter, reason };
}

function isValueAssertionFailure(errors) {
  return /Error: expect\(locator\)|expect\(locator\)\.|Expected:|Expected (pattern|string|value)|Received (string|value)|toBe|toHave|toContain|toEqual/.test(errors);
}

/**
 * Walks Playwright's nested report tree and collects failed test error messages.
 */
function collectPlaywrightErrors(report) {
  const messages = [];
  const stack = [report];

  while (stack.length > 0) {
    const node = stack.pop();
    if (!node) {
      continue;
    }

    if (Array.isArray(node)) {
      stack.push(...node);
    } else if (typeof node === 'object') {
      if ((node.status === 'failed' || node.status === 'timedOut') && node.error?.message) {
        messages.push(node.error.message);
      }
      stack.push(...Object.values(node));
    }
  }

  return messages.join('\n\n---\n\n');
}

function stripAnsi(value) {
  return value.replace(/\u001b\[[0-9;]*m/g, '');
}
