/**
 * Interprets PHPUnit output as an issue verdict rather than a generic test result.
 *
 * In this executor a passing generated test means the shop is healthy, while a PHPUnit failure or
 * configured symptom exception means the reported behavior reproduced.
 */
export function classifyPhpunitOutput(output, plan) {
  const firstError = phpunitFailureBlock(output).replace(/\s+/g, ' ').slice(0, 700);
  // Include the comma form: PHPUnit 10/11 prints "OK, but there were issues!" for a passing run that
  // also raised warnings/risky/deprecation notices.
  if (/^OK[ (,]/m.test(output)) {
    return { status: 'not_reproduced', matched: true, reporter: 'PHPUnit OK (healthy)', reason: null };
  }
  if (/FAILURES!/.test(output)) {
    return { status: 'reproduced', matched: false, reporter: phpunitFailureBlock(output) || 'assertion failed (symptom present)', reason: null };
  }
  // Warnings/risky/deprecation WITHOUT failures or errors is still a passing (healthy) run — the
  // assertions held. FAILURES!/ERRORS! are handled above, so reaching here means neither is present.
  if (/^(WARNINGS|RISKY|DEPRECATIONS)!/m.test(output) && !/ERRORS!/.test(output)) {
    return { status: 'not_reproduced', matched: true, reporter: 'PHPUnit passed with warnings/risky notices (healthy)', reason: null };
  }
  if (/ERRORS!|No tests executed|Fatal error|PHP Fatal|Uncaught/.test(output)) {
    const pattern = plan.assertion?.symptom_pattern;
    if (pattern && new RegExp(pattern).test(output)) {
      return { status: 'reproduced', matched: false, reporter: `symptom exception matched '${pattern}'`, reason: null };
    }
    return {
      status: 'inconclusive',
      matched: null,
      reporter: 'PHPUnit errored (test could not run)',
      reason: `PHPUnit could not run the test (errored before/outside the symptom assertion): ${firstError} -- full output in phpunit-output.txt`,
    };
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
function phpunitFailureBlock(output, max = 1200) {
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
