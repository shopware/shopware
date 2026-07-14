#!/usr/bin/env node
/**
 * Runs the agent-authored bundle once against the current shop and classifies the outcome.
 *
 * This is the trusted leg runner: the deterministic pipeline invokes it (from an immutable /tmp
 * copy) for the reported and trunk legs, gated by REPRO_FREE_ALLOW_RUN=1. The agent's own `repro
 * try` reuses runBundle() in rehearsal mode — same behavior, different output directory, zero
 * authority.
 *
 * One leg = reset DB to the clean snapshot → execute `bash repro/run.sh` (workspace cwd, wall-clock
 * capped) → parse `##repro` markers from the combined log → classify → write result.json, run.log,
 * and the evidence/ files the script produced.
 *
 * Env in:  APP_URL, SW_ACCESS_KEY, ADMIN_USER, ADMIN_PASS, SHOP_DIR, DATABASE_URL.
 * Env out (to run.sh): the same shop coordinates plus EVIDENCE_DIR and BUNDLE_DIR — deliberately
 * NOT which leg is running: a faithful reproduction must behave identically on both.
 */
import fs from 'node:fs';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { execFileSync } from 'node:child_process';
import { BUNDLE, BUNDLE_DIR, OUT, readManifest, writeJson, shopDir, tail, die } from './lib.mjs';
import { parseMarkers, classify } from './markers.mjs';

/**
 * Snapshots the provisioned shop's dirty paths so a post-run diff can show what run.sh changed
 * inside it (plugin installs, config edits). Runtime noise (var/, caches, media) is excluded.
 */
function shopStatus() {
  try {
    return execFileSync('git', ['status', '--porcelain'], { cwd: shopDir(), encoding: 'utf8' })
      .split('\n')
      .filter(Boolean)
      .map((line) => line.slice(3).replace(/^"|"$/g, ''))
      .filter((p) => !/^(var|public|files)\//.test(p));
  } catch {
    return [];
  }
}

/**
 * Restores the clean post-install database snapshot, best-effort: freshly provisioned legs may not
 * have one yet, and a failed restore should surface as leg behavior, not abort the leg.
 */
export function resetDb() {
  if (!fs.existsSync(OUT.snapshot)) {
    console.log('no DB snapshot — running on the current state');
    return;
  }
  if (!process.env.DATABASE_URL) {
    console.warn('::warning::DATABASE_URL not set — skipping DB reset');
    return;
  }
  const u = new URL(process.env.DATABASE_URL);
  const db = {
    host: u.hostname || '127.0.0.1',
    port: u.port || '3306',
    user: decodeURIComponent(u.username) || 'root',
    pass: decodeURIComponent(u.password),
    name: u.pathname.replace(/^\//, ''),
  };
  console.log('resetting DB to the clean snapshot…');
  const restore = spawnSync(
    'bash',
    ['-c', `gunzip -c "${OUT.snapshot}" | mysql -h"${db.host}" -P"${db.port}" -u"${db.user}" "${db.name}"`],
    { stdio: 'inherit', env: { ...process.env, MYSQL_PWD: db.pass } },
  );
  if (restore.status !== 0) {
    console.warn('::warning::DB reset failed — running on the current state');
    return;
  }
  const shop = shopDir();
  if (fs.existsSync(`${shop}/bin/console`)) {
    spawnSync('php', ['bin/console', 'cache:pool:clear', '--all'], {
      cwd: shop,
      stdio: 'ignore',
      env: { ...process.env, APP_ENV: 'prod' },
    });
  }
}

/**
 * Executes run.sh once, teeing its combined output to the console and returning it whole.
 */
function execRun({ timeoutS, evidenceDir }) {
  const env = {
    ...process.env,
    EVIDENCE_DIR: path.resolve(evidenceDir),
    BUNDLE_DIR: path.resolve(BUNDLE_DIR),
    SHOP_DIR: path.resolve(shopDir()),
  };
  // The leg identity must not leak into the script — a faithful bundle behaves the same on both.
  delete env.TARGET;

  return new Promise((resolve) => {
    const started = Date.now();
    let log = '';
    let timedOut = false;
    // detached → its own process group, so a timeout kills the whole tree (browsers, php servers).
    const child = spawn('bash', [BUNDLE.run], { env, detached: true, stdio: ['ignore', 'pipe', 'pipe'] });
    const onData = (chunk) => {
      log += chunk;
      process.stdout.write(chunk);
    };
    child.stdout.on('data', onData);
    child.stderr.on('data', onData);
    const timer = setTimeout(() => {
      timedOut = true;
      try {
        process.kill(-child.pid, 'SIGKILL');
      } catch {
        // Process group already gone.
      }
    }, timeoutS * 1000);
    child.on('close', (code, signal) => {
      clearTimeout(timer);
      resolve({
        exitCode: code ?? (signal ? 137 : 1),
        timedOut,
        log,
        durationS: Math.round((Date.now() - started) / 1000),
      });
    });
  });
}

/**
 * Lists the evidence files the run produced, attaching captions from `##repro evidence` markers.
 */
function collectEvidence(evidenceDir, markers) {
  if (!fs.existsSync(evidenceDir)) {
    return [];
  }
  const captionFor = (name) => markers.evidence.find(
    (e) => e.file === name || path.basename(e.file) === name,
  )?.caption || '';
  return fs.readdirSync(evidenceDir)
    .filter((name) => fs.statSync(path.join(evidenceDir, name)).isFile())
    .sort()
    .map((name) => ({ file: name, caption: captionFor(name) }));
}

/**
 * Runs one leg end to end and writes its outputs into `outDir`.
 *
 * @returns The classified result object (also written as result.json).
 */
export async function runBundle({ target, outDir = '.' }) {
  if (!fs.existsSync(BUNDLE.run)) {
    die(`${BUNDLE.run} not found — the bundle has no executable handle`, 2);
  }
  fs.mkdirSync(outDir, { recursive: true });
  const evidenceDir = path.join(outDir, OUT.evidenceDir);
  fs.rmSync(evidenceDir, { recursive: true, force: true });
  fs.mkdirSync(evidenceDir, { recursive: true });

  const manifest = readManifest();
  resetDb();

  const shopBefore = new Set(shopStatus());
  console.log(`\n── run.sh (${target}, timeout ${manifest.timeout_s}s) ──`);
  const run = await execRun({ timeoutS: manifest.timeout_s, evidenceDir });
  const shopChanges = shopStatus().filter((p) => !shopBefore.has(p));
  const markers = parseMarkers(run.log);
  const { status, blockedReason, inconsistencies } = classify({
    exitCode: run.exitCode,
    timedOut: run.timedOut,
    markers,
    timeoutS: manifest.timeout_s,
  });

  const result = {
    schema_version: 'free-1',
    target,
    status,
    exit_code: run.exitCode,
    timed_out: run.timedOut,
    duration_s: run.durationS,
    blocked_reason: blockedReason,
    observed: markers.observed,
    expected: markers.expected,
    steps: markers.steps,
    inconsistencies,
    evidence: collectEvidence(evidenceDir, markers),
    shop_changes: shopChanges,
    log_tail: tail(run.log),
  };
  fs.writeFileSync(path.join(outDir, OUT.runLog), run.log);
  writeJson(path.join(outDir, OUT.result), result);

  console.log(`\n── ${target}: exit ${run.exitCode}${run.timedOut ? ' (timed out)' : ''} → ${status}`
    + `${blockedReason ? ` (${blockedReason})` : ''} ──`);
  for (const note of inconsistencies) {
    console.warn(`::warning::inconsistent signals: ${note}`);
  }
  return result;
}

/**
 * Writes a synthesized blocked result for a leg that could not run at all (e.g. a failed trunk
 * provision), so the verdict reads "couldn't run" instead of a bare missing leg.
 */
export function writeBlockedResult(target, reason, outDir = '.') {
  fs.mkdirSync(outDir, { recursive: true });
  writeJson(path.join(outDir, OUT.result), {
    schema_version: 'free-1',
    target,
    status: 'blocked',
    exit_code: null,
    timed_out: false,
    duration_s: 0,
    blocked_reason: reason,
    observed: [],
    expected: [],
    steps: [],
    inconsistencies: [],
    evidence: [],
    shop_changes: [],
    log_tail: '',
  });
  console.log(`wrote blocked ${target} result: ${reason}`);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const [command, ...args] = process.argv.slice(2);
  if (command === 'blocked-result') {
    const [target, reason] = args;
    if (!target) {
      die('blocked-result: missing <target>', 2);
    }
    writeBlockedResult(target, reason || `${target} environment did not come up`);
  } else {
    const target = command;
    if (!['reported', 'trunk'].includes(target)) {
      die(`usage: run-bundle.mjs <reported|trunk> | blocked-result <target> [reason]`, 2);
    }
    if (process.env.REPRO_FREE_ALLOW_RUN !== '1') {
      die('trusted leg runs are reserved for the deterministic pipeline (REPRO_FREE_ALLOW_RUN=1)', 2);
    }
    await runBundle({ target });
  }
}
