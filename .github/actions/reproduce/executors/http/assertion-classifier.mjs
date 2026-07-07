import { spawnSync } from 'node:child_process';

const OPS = {
  equals: (a, e) => a === e,
  contains: (a, e) => a.includes(e),
  matches: (a, e) => new RegExp(e).test(a),
  present: (a) => !['', '<unparseable>', 'null', '[]', '{}'].includes(a),
  absent: (a) => ['', '<unparseable>', 'null'].includes(a),
  gt: (a, e) => Number(a) > Number(e),
  lt: (a, e) => Number(a) < Number(e),
};

/**
 * Evaluates HTTP plan assertions against the final response and explains the verdict.
 *
 * It distinguishes symptom failures from setup/auth failures so a rejected harness credential or
 * unreadable error response does not get mislabeled as the reported bug.
 */
export function classifyHttpAssertions(assertions, { code, bodyText, blocked }) {
  const initialOutcome = initialAssertionOutcome(assertions, { code, blocked });
  if (initialOutcome) {
    return initialOutcome;
  }

  const checks = [];
  let outcome = null;

  for (const assertion of assertions) {
    const check = evaluateAssertion(assertion, { code, bodyText }, outcome !== null);
    checks.push(check);
    if (outcome) {
      continue;
    }
    if (!check.ok) {
      outcome = outcomeForFailedCheck(check, code);
    }
  }

  return outcome ? { ...outcome, checks } : { status: 'not_reproduced', checks, reporter: `all assertions passed; HTTP ${code}` };
}

/**
 * Handles sequence-level blockers before field assertions inspect the response body.
 */
function initialAssertionOutcome(assertions, { code, blocked }) {
  if (blocked) {
    return { status: 'blocked', checks: [], reporter: blocked };
  }
  if (assertions.length === 0) {
    return { status: 'inconclusive', checks: [], reporter: 'the plan declares no assertions' };
  }
  if (isRejectedAuthWithoutAssertion(assertions, code)) {
    return {
      status: 'inconclusive',
      checks: [],
      reporter: `request returned HTTP ${code} (auth rejected) before the symptom could run -- harness-credential failure, not the reported bug`,
    };
  }

  return null;
}

function evaluateAssertion(assertion, response, skipped) {
  const check = assertionCheck(assertion);
  if (skipped) {
    return { ...check, actual: '(not run)', ok: null };
  }

  const actual = check.kind === 'http_status'
    ? response.code
    : (jqField(assertion.field, response.bodyText) || '<unparseable>');

  return {
    ...check,
    actual,
    ok: OPS[check.op](actual, check.expected),
  };
}

function assertionCheck(assertion) {
  const kind = assertion.kind || (assertion.field ? 'response_field' : 'http_status');

  return {
    role: assertion.role === 'precondition' ? 'precondition' : 'assert',
    kind,
    subject: kind === 'http_status' ? 'status' : `response | ${assertion.field}`,
    op: OPS[assertion.op] ? assertion.op : 'equals',
    expected: assertion.expect !== undefined ? String(assertion.expect) : '',
    label: assertion.label || assertion.comment || '',
  };
}

/**
 * Maps the first failed assertion to reproduced or inconclusive based on its role.
 */
function outcomeForFailedCheck(check, code) {
  if (check.role === 'precondition') {
    return {
      status: 'inconclusive',
      reporter: `precondition not met: ${check.subject} (expected ${check.expected}, got ${check.actual}) -- the scenario was not set up as expected`,
    };
  }
  if (isUnreadableValueCheck(check) && !is2xx(code)) {
    return { status: 'inconclusive', reporter: `${check.subject} was unreadable on HTTP ${code} -- can't confirm the symptom` };
  }

  return { status: 'reproduced', reporter: `${check.subject} failed (expected ${check.expected}, got ${check.actual}); HTTP ${code}` };
}

function isRejectedAuthWithoutAssertion(assertions, code) {
  return ['401', '403'].includes(code) && !assertions.some((assertion) => isAuthStatusAssertion(assertion));
}

function isAuthStatusAssertion(assertion) {
  return isStatusAssertion(assertion) && ['401', '403'].includes(String(assertion.expect));
}

function isStatusAssertion(assertion) {
  return assertion.kind === 'http_status' || (assertion.kind === undefined && assertion.field === undefined);
}

function isUnreadableValueCheck({ op, actual }) {
  return ['equals', 'contains', 'matches', 'gt', 'lt'].includes(op) && actual === '<unparseable>';
}

function is2xx(code) {
  return /^2/.test(code);
}

function jqField(filter, body) {
  return (spawnSync('jq', ['-r', filter], { input: body, encoding: 'utf8' }).stdout || '').trim();
}
