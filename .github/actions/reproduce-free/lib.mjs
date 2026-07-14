/**
 * Shared helpers and the (tiny) bundle contract for the FREE reproduce variant.
 *
 * The free variant fixes only two file names inside the agent-authored `repro/` directory:
 * `run.sh` (the executable handle) and `comment.md` (the report template). Everything else the
 * agent creates is free-form. Harness-owned outputs are named here so the runner, CLI, renderer,
 * and workflow steps agree on one layout.
 */
import fs from 'node:fs';
import path from 'node:path';

/** The agent-authored bundle directory (everything the agent produces lives inside). */
export const BUNDLE_DIR = 'repro';

/** Fixed file names inside the bundle — the entire authored contract. */
export const BUNDLE = {
  run: `${BUNDLE_DIR}/run.sh`,           // required: exit 0 healthy, 1 bug observed, >=2 blocked
  comment: `${BUNDLE_DIR}/comment.md`,   // required: report template with {{...}} placeholders
  manifest: `${BUNDLE_DIR}/manifest.json`, // optional: build/timeout knobs with defaults
};

/** Harness-owned outputs written around the bundle. */
export const OUT = {
  result: 'result.json',                 // one leg's classified outcome (written only by run-bundle)
  runLog: 'run.log',                     // that leg's combined stdout+stderr
  evidenceDir: 'evidence',               // files the run drops for the comment (screenshots, dumps)
  snapshot: 'repro-clean-db.sql.gz',     // clean post-install DB (taken pre-agent, shared name with the strict variant)
  giveup: 'giveup.txt',
  summary: 'agent-summary.md',
  filesChanged: 'files-changed.txt',     // every file the agent added/modified (post-step audit)
  shopSrcEdits: 'shop-src-edits.txt',    // edits under shop/src — always a human-review flag
};

/** Manifest knobs with their defaults; unknown keys are ignored, absent files mean all defaults. */
export const MANIFEST_DEFAULTS = {
  admin_build: false,       // trunk leg: build the Administration
  storefront_build: false,  // trunk leg: build the Storefront
  demodata: false,          // trunk leg: install demo data
  timeout_s: 600,           // run.sh wall clock (capped at 1800)
};

export const TIMEOUT_CAP_S = 1800;

/**
 * Reads the bundle manifest merged over its defaults; tolerant of a missing or broken file so a
 * forgotten manifest never blocks a run.
 */
export function readManifest(dir = '.') {
  const merged = { ...MANIFEST_DEFAULTS, ...readJson(path.join(dir, BUNDLE.manifest), {}) };
  merged.timeout_s = Math.min(Number(merged.timeout_s) || MANIFEST_DEFAULTS.timeout_s, TIMEOUT_CAP_S);
  return merged;
}

/**
 * Reads a JSON file with an optional fallback for absent or invalid content.
 */
export function readJson(file, fallback = undefined) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (err) {
    if (fallback !== undefined) {
      return fallback;
    }
    throw err;
  }
}

/**
 * Writes stable, newline-terminated JSON for workflow artifacts.
 */
export const writeJson = (file, value) => fs.writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`);

/**
 * Returns the provisioned Shopware base URL without a trailing slash.
 */
export const appUrl = () => (process.env.APP_URL || '').replace(/\/$/, '');

/**
 * Returns the local checkout path for commands that must run inside Shopware.
 */
export const shopDir = () => process.env.SHOP_DIR || 'shop';

/**
 * Redacts common secret token formats before any text leaves the workflow.
 */
export const redact = (text) => String(text)
  .replace(/sk-ant-[A-Za-z0-9_-]{8,}/g, '[REDACTED_KEY]')
  .replace(/(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/github_pat_[A-Za-z0-9_]{20,}/g, '[REDACTED_TOKEN]')
  .replace(/AKIA[0-9A-Z]{16}/g, '[REDACTED_AWS_KEY]')
  .replace(/([Bb]earer\s+)[A-Za-z0-9._~+/-]{16,}=*/g, '$1[REDACTED]');

/**
 * Caps text to its last `maxLines`/`maxChars`, marking the cut — logs matter most at the end.
 */
export function tail(text, maxLines = 120, maxChars = 8000) {
  const lines = String(text).split('\n');
  let out = lines.length > maxLines ? lines.slice(-maxLines).join('\n') : String(text);
  if (out.length > maxChars) {
    out = out.slice(-maxChars);
  }
  return out.length < String(text).length ? `… (truncated)\n${out}` : out;
}

/**
 * Exits the CLI with a consistent `repro:` error prefix.
 */
export function die(message, code = 1) {
  console.error(`repro: ${message}`);
  process.exit(code);
}
