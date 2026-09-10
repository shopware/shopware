import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { computeVerdict } from './verdict.ts';

interface ArtOpts {
  reported?: string;
  trunk?: string;
  plan?: Record<string, unknown>;
  edits?: string;
}

// Lay out an `artifacts/` tree the way the workflow uploads it, then compute the verdict against it.
function art({ reported, trunk, plan = { confidence: 1 }, edits }: ArtOpts = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'repro-verdict-'));
  fs.mkdirSync(path.join(dir, 'repro-plan'), { recursive: true });
  fs.writeFileSync(path.join(dir, 'repro-plan', 'reproduction-plan.json'), JSON.stringify(plan));
  if (edits !== undefined) fs.writeFileSync(path.join(dir, 'repro-plan', 'workspace-edits.txt'), edits);
  const leg = (name: string, status?: string) => {
    if (status === undefined) return;
    fs.mkdirSync(path.join(dir, name), { recursive: true });
    fs.writeFileSync(path.join(dir, name, 'result.json'), JSON.stringify({ status, evidence: { reporter_output: `${status} reporter` } }));
  };
  leg('repro-reported', reported);
  leg('repro-trunk', trunk);
  return dir;
}
const verdictOf = (opts: ArtOpts) => computeVerdict(art(opts)).verdict;

test('no leg results at all -> has_results false', () => {
  assert.equal(computeVerdict(art({})).has_results, false);
});

test('the status matrix (with a trusted plan)', () => {
  assert.equal(verdictOf({ reported: 'reproduced', trunk: 'reproduced' }), 'live_bug');
  assert.equal(verdictOf({ reported: 'reproduced', trunk: 'not_reproduced' }), 'fixed_on_trunk');
  assert.equal(verdictOf({ reported: 'not_reproduced', trunk: 'reproduced' }), 'regression');
  assert.equal(verdictOf({ reported: 'not_reproduced', trunk: 'not_reproduced' }), 'not_reproducible');
});

test('a missing reported leg + trunk reproduced is still a live_bug', () => {
  assert.equal(verdictOf({ trunk: 'reproduced' }), 'live_bug');
});

test('a missing reported leg + trunk not_reproduced is NOT a confident closure', () => {
  // The deliberately-omitted matrix cell: falls through to needs_human_review.
  assert.equal(verdictOf({ trunk: 'not_reproduced' }), 'needs_human_review');
});

test('a blocked leg takes precedence over the matrix', () => {
  assert.equal(verdictOf({ reported: 'blocked', trunk: 'reproduced' }), 'blocked');
});

test('confidence below 0.7 downgrades to needs_human_review', () => {
  const r = computeVerdict(art({ reported: 'reproduced', trunk: 'reproduced', plan: { confidence: 0.5 } }));
  assert.equal(r.verdict, 'needs_human_review');
  assert.match(r.unsure_reason, /0\.5|threshold/);
});

test('a non-numeric confidence is treated as fully untrusted (0)', () => {
  assert.equal(verdictOf({ reported: 'reproduced', trunk: 'reproduced', plan: { confidence: 'high' } }), 'needs_human_review');
});

test('an inconclusive leg -> needs_human_review, surfacing that leg reason', () => {
  const r = computeVerdict(art({ reported: 'inconclusive', trunk: 'not_reproduced' }));
  assert.equal(r.verdict, 'needs_human_review');
  assert.match(r.unsure_reason, /inconclusive reporter/);
});

test('editing the provisioned shop/src downgrades to needs_human_review', () => {
  const r = computeVerdict(art({ reported: 'reproduced', trunk: 'not_reproduced', edits: 'shop/src/Core/Foo.php\n' }));
  assert.equal(r.verdict, 'needs_human_review');
  assert.match(r.unsure_reason, /patched Shopware core|shop\/src/);
});
