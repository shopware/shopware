#!/usr/bin/env node
/**
 * Disclosure audit: which files did the agent add or change, and did it touch the shop's own code?
 *
 * The free variant does not forbid edits — it makes omission louder than disclosure. Every changed
 * file the agent does not reference via `{{file:…}}` in its comment template is called out in the
 * posted comment; edits under the provisioned shop's src/ or custom/ additionally downgrade the
 * verdict to needs_human_review, because the trusted reported leg runs on this very shop.
 *
 * Used two ways: as the post-agent workflow step (baselines from /tmp, writes the audit files) and
 * from `repro render --dry` so the agent can see what would be called out before it stops.
 */
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { BUNDLE, OUT, shopDir } from './lib.mjs';

/** Harness-known paths that never count as agent disclosure debt. */
const IGNORED = [
  BUNDLE.comment, BUNDLE.manifest,          // meta files, always attached to the run
  OUT.result, OUT.runLog, OUT.giveup, OUT.summary, OUT.filesChanged, OUT.shopSrcEdits,
  OUT.snapshot, 'context.md', 'issue.md', 'comment.md', 'evidence.json',
];
const IGNORED_PREFIXES = [
  `${OUT.evidenceDir}/`, '.repro-try/', 'issue-assets/', 'node_modules/', 'shop/',
  'test-results/', 'playwright-report/', '.playwright-cli',
];
const IGNORED_FILES = ['package.json', 'package-lock.json'];

/**
 * Parses `git status --porcelain` output into plain paths.
 */
const statusPaths = (text) => String(text).split('\n')
  .filter(Boolean)
  .map((line) => line.slice(3).replace(/^"|"$/g, ''));

/**
 * Runs `git status --porcelain -uall`, tolerating a missing repo (returns no paths). `-uall` lists
 * untracked files individually — without it a new directory collapses to one `dir/` entry and the
 * per-file disclosure comparison against `{{file:…}}` references can never match.
 */
function gitStatus(cwd = '.') {
  try {
    return execFileSync('git', ['status', '--porcelain', '-uall'], { cwd, encoding: 'utf8' });
  } catch {
    return '';
  }
}

/**
 * Reads a pre-agent baseline status file; a missing baseline means "everything new counts".
 */
const readBaseline = (file) => {
  try {
    return fs.readFileSync(file, 'utf8');
  } catch {
    return '';
  }
};

/**
 * Computes the agent's changed files (workspace) and shop-code edits (nested checkout).
 *
 * @returns {{changed: string[], shopEdits: string[]}} `changed` is disclosure debt for the comment
 * audit; `shopEdits` (shop/src, shop/custom) always flags human review.
 */
export function auditFiles({
  baselineFile = '/tmp/repro-pre-status.txt',
  shopBaselineFile = '/tmp/repro-pre-shop-status.txt',
} = {}) {
  const before = new Set(statusPaths(readBaseline(baselineFile)));
  const changed = statusPaths(gitStatus('.'))
    .filter((p) => !before.has(p))
    .filter((p) => !IGNORED.includes(p)
      && !IGNORED_FILES.includes(p)
      && !IGNORED_PREFIXES.some((prefix) => p === prefix.replace(/\/$/, '') || p.startsWith(prefix)));

  const shopBefore = new Set(statusPaths(readBaseline(shopBaselineFile)));
  const shopEdits = statusPaths(gitStatus(shopDir()))
    .filter((p) => !shopBefore.has(p))
    .filter((p) => p.startsWith('src/') || p.startsWith('custom/'))
    .map((p) => `${shopDir()}/${p}`);

  return { changed, shopEdits };
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const { changed, shopEdits } = auditFiles();
  fs.writeFileSync(OUT.filesChanged, changed.length ? `${changed.join('\n')}\n` : '');
  fs.writeFileSync(OUT.shopSrcEdits, shopEdits.length ? `${shopEdits.join('\n')}\n` : '');
  console.log(`changed files: ${changed.length ? `\n  ${changed.join('\n  ')}` : 'none'}`);
  if (shopEdits.length) {
    console.warn(`::warning::agent edited the provisioned shop's own code:\n  ${shopEdits.join('\n  ')}`);
  }
}
