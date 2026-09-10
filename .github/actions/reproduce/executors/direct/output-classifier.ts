import type { LegStatus, Plan } from '../../types.ts';

/** The verdict fragment the direct executor derives from PHPUnit's process output. */
export interface PhpunitClassification {
  status: LegStatus;
  matched: boolean | null;
  reporter: string;
  reason: string | null;
}

/**
 * Interprets PHPUnit output as an issue verdict rather than a generic test result.
 *
 * In this executor a passing generated test means the shop is healthy, while a PHPUnit failure or
 * configured symptom exception means the reported behavior reproduced.
 */
export function classifyPhpunitOutput(output: string, plan: Partial<Plan>): PhpunitClassification {
  const failureBlock = phpunitFailureBlock(output);
  const firstError = failureBlock.replace(/\s+/g, ' ').slice(0, 700);
  // Order matters: check the authoritative failure/error signals FIRST, so a captured line that merely
  // starts with "OK" (in a test's own stdout) can't flip a real failure to healthy.
  // A failure/error only counts as REPRODUCED when it matches the plan's symptom marker
  // (assertion.symptom_pattern, required for direct plans by validate.ts). Otherwise it's most
  // likely a failed setup/precondition assertion — NOT the reported symptom — so it's inconclusive,
  // never a false `reproduced`. The symptom assertion must carry a distinctive token in its
  // failure/exception message that this pattern matches.
  //
  // Match the pattern against the FIRST failure paragraph (method-name header stripped by
  // phpunitFailureBlock), not the whole output: otherwise the token appearing in an echoed test
  // source line, a stack-trace path, or the test method name would flip a failed setup assertion to
  // a false `reproduced`. Fall back to the full output only for hard fatals with no failure block.
  const symptomPattern = plan.assertion?.symptom_pattern;
  const matchesSymptom = symptomPattern ? new RegExp(symptomPattern).test(failureBlock || output) : false;
  if (/FAILURES!/.test(output)) {
    if (matchesSymptom) {
      return { status: 'reproduced', matched: false, reporter: phpunitFailureBlock(output) || `symptom assertion failed (matched '${symptomPattern}')`, reason: null };
    }
    return {
      status: 'inconclusive',
      matched: null,
      reporter: 'PHPUnit failed, but not the marked symptom assertion',
      reason: `a PHPUnit assertion failed but it did not match the symptom marker (assertion.symptom_pattern${symptomPattern ? ` = '${symptomPattern}'` : ' is not set'}) — likely a failed setup/precondition, not the reported symptom: ${firstError}`,
    };
  }
  if (/ERRORS!|No tests executed|Fatal error|PHP Fatal|Uncaught/.test(output)) {
    if (matchesSymptom) {
      return { status: 'reproduced', matched: false, reporter: `symptom exception matched '${symptomPattern}'`, reason: null };
    }
    return {
      status: 'inconclusive',
      matched: null,
      reporter: 'PHPUnit errored (test could not run)',
      reason: `PHPUnit could not run the test (errored before/outside the symptom assertion): ${firstError} -- full output in phpunit-output.txt`,
    };
  }
  // Healthy: anchor to PHPUnit's actual summary line — "OK (5 tests, …)" or the 10/11
  // "OK, but there were issues!" form — not any line that happens to start with "OK".
  if (/^OK \(\d+ test/m.test(output) || /^OK, but /m.test(output)) {
    return { status: 'not_reproduced', matched: true, reporter: 'PHPUnit OK (healthy)', reason: null };
  }
  // Warnings/risky/deprecation with no failures or errors (both handled above) is still a passing run.
  if (/^(WARNINGS|RISKY|DEPRECATIONS)!/m.test(output)) {
    return { status: 'not_reproduced', matched: true, reporter: 'PHPUnit passed with warnings/risky notices (healthy)', reason: null };
  }

  return {
    status: 'blocked',
    matched: null,
    reporter: 'PHPUnit produced no result',
    reason: `PHPUnit produced no recognisable result: ${output.slice(-1500).replace(/\s+/g, ' ')}`,
  };
}

/**
 * Extracts the first useful PHPUnit failure paragraph for compact reporter evidence.
 */
function phpunitFailureBlock(output: string, max = 1200): string {
  const start = output.search(/^\d+\) /m);
  if (start === -1) {
    return '';
  }
  const rest = output.slice(start);
  const end = rest.slice(3).search(/^(\d+\) |FAILURES!|ERRORS!|WARNINGS!|OK\b|Tests: )/m);
  let block = (end === -1 ? rest : rest.slice(0, end + 3)).trim();
  block = block.replace(/^\d+\) .*(\n|$)/, '');
  block = block.replace(/^\s*\/\S*\/([^/\s]+\.php:\d+)\s*$/m, '$1');
  block = block.trim();

  return block.length > max ? `${block.slice(0, max)}\n...` : block;
}
