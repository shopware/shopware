/**
 * The `##repro` output marker protocol — the only structured channel between the agent-authored
 * run.sh (any language: bash, PHP, a thrown JS error) and the harness.
 *
 * One marker per line, anywhere in stdout/stderr:
 *
 *   ##repro blocked <reason>                 setup/environment failure — overrides the exit code
 *   ##repro observed <what actually happened, with the real runtime value>
 *   ##repro expected <what a healthy shop shows>
 *   ##repro step <narration of what the script is doing right now>
 *   ##repro evidence <file-in-$EVIDENCE_DIR> :: <caption>
 *
 * Markers are only ever trusted from the harness-executed legs; anything an agent prints during
 * its own rehearsal is feedback for the agent, nothing more.
 */

const MARKER = /^\s*(?:.*?)##repro\s+(blocked|observed|expected|step|evidence)\s+(.+?)\s*$/;

/**
 * Parses all `##repro` markers out of a combined run log.
 *
 * @returns {{blocked: string[], observed: string[], expected: string[], steps: string[],
 *            evidence: Array<{file: string, caption: string}>}}
 */
export function parseMarkers(log) {
  const out = { blocked: [], observed: [], expected: [], steps: [], evidence: [] };
  for (const line of String(log).split('\n')) {
    const m = line.match(MARKER);
    if (!m) {
      continue;
    }
    const [, kind, rest] = m;
    if (kind === 'evidence') {
      const [file, ...caption] = rest.split('::');
      out.evidence.push({ file: file.trim(), caption: caption.join('::').trim() });
    } else {
      out[kind === 'step' ? 'steps' : kind].push(rest.trim());
    }
  }
  return out;
}

/**
 * Classifies one leg from its exit code + markers. The rules, in precedence order:
 *
 *   1. a `blocked` marker always wins — a setup failure must never read as a reproduction,
 *      no matter how the script exited;
 *   2. a timeout is blocked;
 *   3. exit 0 → not_reproduced (healthy), exit 1 → reproduced (bug observed), anything else → blocked.
 *
 * Inconsistencies (e.g. a happy exit 0 alongside a `blocked` marker) don't change the status beyond
 * rule 1 but are recorded so the comment can surface the mismatch instead of hiding it.
 */
export function classify({ exitCode, timedOut, markers, timeoutS }) {
  const inconsistencies = [];
  if (markers.blocked.length && exitCode === 0) {
    inconsistencies.push(`the script printed '##repro blocked' but exited 0 (healthy)`);
  }
  if (markers.blocked.length && exitCode === 1) {
    inconsistencies.push(`the script printed '##repro blocked' but exited 1 (bug observed)`);
  }

  if (markers.blocked.length) {
    return { status: 'blocked', blockedReason: markers.blocked[0], inconsistencies };
  }
  if (timedOut) {
    return { status: 'blocked', blockedReason: `run.sh timed out after ${timeoutS}s`, inconsistencies };
  }
  if (exitCode === 0) {
    return { status: 'not_reproduced', blockedReason: null, inconsistencies };
  }
  if (exitCode === 1) {
    return { status: 'reproduced', blockedReason: null, inconsistencies };
  }
  return {
    status: 'blocked',
    blockedReason: `run.sh exited ${exitCode} (setup/environment failure by contract)`,
    inconsistencies,
  };
}
