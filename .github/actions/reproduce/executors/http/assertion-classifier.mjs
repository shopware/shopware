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
  // No response was captured (e.g. an empty `requests: []`) — the symptom never ran, so a failed
  // status assertion here is a plan defect, not the reported bug. Route it to human review rather
  // than letting `'' !== '200'` masquerade as a reproduction.
  if (!code) {
    return { status: 'inconclusive', checks: [], reporter: 'no HTTP response was captured -- the plan ran no requests' };
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

  if (check.kind === 'http_status') {
    return { ...check, actual: response.code, ok: OPS[check.op](response.code, check.expected) };
  }

  const { value, error } = jqField(assertion.field, response.bodyText);
  return { ...check, actual: value, jqError: error, ok: OPS[check.op](value, check.expected) };
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
  if (isUnreadableValueCheck(check)) {
    // An unreadable value comparison is never trustworthy symptom evidence, on ANY status: a bad
    // filter (jq compile error), a non-JSON body, or a wrong-shape access all land here, and each is
    // an authoring/harness problem far more often than the reported bug. Route to human review and
    // surface jq's own message so the agent can fix the filter during `repro try` — or, when the
    // shape itself is the symptom, re-express it as a readable `.field | type` / present / absent.
    const detail = check.jqError ? ` -- jq: ${check.jqError}` : '';
    return { status: 'inconclusive', reporter: `${check.subject} could not be read from the HTTP ${code} response${detail} -- can't confirm the symptom` };
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

// Condenses jq's stderr into one readable line so a failed extraction explains itself — a compile
// error (bad filter), a `parse error` (the body was not JSON), or a runtime error (wrong shape) —
// instead of surfacing a bare `<unparseable>` to the agent and the verdict comment.
function jqErrorSummary(res) {
  const stderr = (res.stderr || '').replace(/\s+/g, ' ').replace(/^jq:\s*/, '').trim();
  return stderr ? stderr.slice(0, 200) : `jq exited ${res.status ?? 'abnormally'}`;
}

// The agent-authored assertion `field` is a full jq PROGRAM, not a passive path, so jq must never see
// the runner's environment: jq's `env`/`$ENV` builtins read the process env regardless of the input
// body, so a field like `$ENV.ACTIONS_RUNTIME_TOKEN` or `env.DATABASE_URL` would exfiltrate a secret
// into `check.actual` and thence the public verdict comment (redact() only catches a few token
// shapes). The http leg otherwise runs host-side, so this is the one path where agent-authored logic
// meets the ambient secret env. Under the CI sandbox we run jq inside an egress-locked
// (`--network none`) container with NO env passed through; everywhere else (local `repro try`) we run
// host jq with the environment scrubbed to PATH only. Either way the filter can only reach the body.
const JQ_SANDBOX = process.env.REPRO_SANDBOX === '1' && !!process.env.REPRO_SANDBOX_JQ_IMAGE;

function runJq(filter, body) {
  if (JQ_SANDBOX) {
    return spawnSync('docker', [
      'run', '--rm', '-i',
      '--network', 'none', // no egress; a jq builtin cannot reach anything
      '--user', `${process.getuid()}:${process.getgid()}`,
      process.env.REPRO_SANDBOX_JQ_IMAGE,
      'jq', '-r', filter,
    ], { input: body, encoding: 'utf8' });
  }
  return spawnSync('jq', ['-r', filter], { input: body, encoding: 'utf8', env: { PATH: process.env.PATH || '' } });
}

/**
 * Extracts a response field with jq, distinguishing a jq failure from an empty value.
 *
 * Returns `{ value, error }`. A non-zero jq exit (invalid filter, non-JSON body, or wrong-shape
 * access) yields `{ value: '<unparseable>', error: <jq message> }`; a field whose real value is the
 * empty string returns `{ value: '', error: null }` so `present`/`absent` and value assertions judge
 * it correctly rather than treating every empty field as unreadable.
 */
function jqField(filter, body) {
  const res = runJq(filter, body);
  if (res.status !== 0) {
    return { value: '<unparseable>', error: jqErrorSummary(res) };
  }
  return { value: (res.stdout || '').trim(), error: null };
}
