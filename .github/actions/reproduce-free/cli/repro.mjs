#!/usr/bin/env node
/**
 * Terminal-facing `repro` command for the FREE reproduce variant — the agent's feedback tools.
 *
 * Everything here is rehearsal/preview only; nothing it produces is authoritative. The trusted
 * legs are executed by the deterministic pipeline via run-bundle.mjs from an immutable copy.
 *
 *   repro init               scaffold repro/ (run.sh, comment.md, manifest.json) — start here
 *   repro try                rehearse the bundle against the live shop (result in .repro-try/)
 *   repro render             preview the final comment body from the last `repro try`
 *   repro reset              restore the clean DB snapshot
 *   repro giveup "<reason>"  record that no reliable reproduction was found
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { BUNDLE, BUNDLE_DIR, OUT, readJson, die } from '../lib.mjs';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const TRY_DIR = '.repro-try';

/**
 * Copies the boilerplate bundle into repro/ without overwriting anything the agent already wrote.
 */
function init() {
  fs.mkdirSync(BUNDLE_DIR, { recursive: true });
  const created = [];
  for (const name of ['run.sh', 'comment.md', 'manifest.json']) {
    const target = path.join(BUNDLE_DIR, name);
    if (fs.existsSync(target)) {
      continue;
    }
    fs.copyFileSync(path.join(root, 'scaffold', name), target);
    created.push(target);
  }
  fs.chmodSync(BUNDLE.run, 0o755);
  console.log(created.length ? `scaffolded: ${created.join(', ')}` : 'repro/ already scaffolded — nothing overwritten');
  console.log('\nContract: run.sh exits 0 = healthy, 1 = bug observed, >=2 = setup failure.');
  console.log('Speak through ##repro markers (blocked/observed/expected/step/evidence).');
  console.log('Rehearse with `repro try`, preview the comment with `repro render`.');
}

/**
 * Rehearses the bundle against the live shop. Same runner as the trusted legs, but the outcome
 * lands in .repro-try/ and carries no authority — it exists so the agent can iterate honestly.
 */
async function tryBundle() {
  const { runBundle } = await import('../run-bundle.mjs');
  const result = await runBundle({ target: 'rehearsal', outDir: TRY_DIR });
  console.log(`\nrehearsal artifacts: ${TRY_DIR}/result.json, ${TRY_DIR}/run.log, ${TRY_DIR}/evidence/`);
  if (result.status === 'reproduced' && !result.observed.length) {
    console.log('hint: exit 1 with no `##repro observed` marker — report the real runtime value you saw.');
  }
  console.log('when the rehearsal is faithful, preview the comment with `repro render`, then stop.');
}

/**
 * Previews the comment body the report job would render, using the last rehearsal as the
 * reported leg, and lists the files that would be called out as undisclosed.
 */
async function render() {
  if (!fs.existsSync(BUNDLE.comment)) {
    die(`${BUNDLE.comment} not found — run \`repro init\` first`, 2);
  }
  const { createResolver, renderBody, referencedFiles } = await import('../report/render-comment.mjs');
  const { auditFiles } = await import('../audit-files.mjs');

  const rehearsal = readJson(`${TRY_DIR}/result.json`, null);
  if (!rehearsal) {
    console.log('note: no rehearsal yet (`repro try`) — run placeholders will render as gaps.\n');
  }
  const template = fs.readFileSync(BUNDLE.comment, 'utf8');
  const resolver = createResolver({
    legs: { reported: rehearsal ? { ...rehearsal, target: 'reported' } : null, trunk: null },
    evidence: rehearsal ? {
      legs: [{
        name: 'reported',
        files: rehearsal.evidence.map((e) => ({ ...e, url: `${TRY_DIR}/evidence/${e.file}` })),
      }],
    } : null,
    readFile: (p) => {
      try {
        return fs.readFileSync(p, 'utf8');
      } catch {
        return null;
      }
    },
  });

  const preview = renderBody(template, resolver);
  const out = `${TRY_DIR}/comment-preview.md`;
  fs.mkdirSync(TRY_DIR, { recursive: true });
  fs.writeFileSync(out, `${preview}\n`);
  console.log(`${preview}\n\n── preview written to ${out} (trunk placeholders stay open until the pipeline runs) ──`);

  const { changed, shopEdits } = auditFiles();
  const disclosed = new Set(referencedFiles(template));
  const undisclosed = changed.filter((p) => !disclosed.has(p));
  if (undisclosed.length) {
    console.log(`\n⚠️  these changed files are NOT referenced via {{file:…}} and will be called out as undisclosed:\n  ${undisclosed.join('\n  ')}`);
  } else {
    console.log('\n✓ every changed file is referenced in the report');
  }
  if (shopEdits.length) {
    console.log(`\n⚠️  you edited the provisioned shop's own code — the verdict will be downgraded to needs_human_review:\n  ${shopEdits.join('\n  ')}`);
  }
}

const commands = {
  init: async () => init(),
  try: tryBundle,
  render,
  reset: async () => (await import('../run-bundle.mjs')).resetDb(),
  giveup: async (args) => {
    fs.writeFileSync(OUT.giveup, `${args.join(' ') || 'no reliable reproduction found'}\n`);
    console.log('recorded give-up; the deterministic report will post an "incomplete" comment');
  },
};

const [command, ...args] = process.argv.slice(2);
const run = commands[command];
if (!run) {
  console.error(`repro: unknown command ${command ? `'${command}'` : '(none)'}\n`);
  console.error('commands: init | try | render | reset | giveup');
  process.exit(2);
}
await run(args);
