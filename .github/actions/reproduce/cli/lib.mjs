// Shared building blocks for the reproduce CLI: the file names that make up a bundle,
// the placeholder vocabulary fixtures may use, and small IO/result helpers. Every other
// cli/ module imports from here, so the bundle contract lives in exactly one place.
import fs from 'node:fs';

// The files the agent authors (the "bundle") and the files the tooling produces around them.
export const FILES = {
  plan: 'reproduction-plan.json',   // the deterministic handoff the whole pipeline reads
  fixtures: 'fixtures.json',         // optional Admin Sync payload seeded before the run
  specTs: 'repro.spec.ts',           // playwright executor artifact
  testPhp: 'ReproTest.php',          // direct (PHPUnit) executor artifact
  result: 'result.json',             // the OFFICIAL leg result (written only by `verify`)
  builderResult: 'builder-result.json', // the agent's own non-authoritative `try` feedback
  snapshot: 'repro-clean-db.sql.gz', // clean post-install DB, taken before the agent runs
  seedError: 'seed-error.txt',       // last seeding error detail, surfaced in the report
};

export const EXECUTORS = ['playwright', 'http', 'direct'];
export const LAYERS = ['storefront-ui', 'admin-ui', 'store-api', 'admin-api', 'service'];

// Install-specific ids resolved against the running shop (see admin-api.mjs). Fixtures and HTTP
// plans reference these by name so one bundle runs on any freshly-provisioned instance — a literal
// UUID would seed on one shop and FK-fail on the next.
export const ENTITY_PLACEHOLDERS = [
  'SC', 'NAV_CAT', 'COUNTRY', 'SALUTATION', 'SALUTATION2', 'TAX', 'CURRENCY',
  'LANGUAGE', 'SYSTEM_LANGUAGE', 'CUSTOMER_GROUP', 'PAYMENT_METHOD', 'SHIPPING_METHOD',
  'ORDER_STATE_OPEN', 'ORDER_DELIVERY_STATE_OPEN', 'ORDER_TRANSACTION_STATE_OPEN',
];

export const appUrl = () => (process.env.APP_URL || '').replace(/\/$/, '');
export const shopDir = () => process.env.SHOP_DIR || 'shop';
export const adminUser = () => process.env.SW_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin';
export const adminPass = () => process.env.SW_ADMIN_PASS ?? process.env.ADMIN_PASS ?? 'shopware';

export function readJson(path, fallback = undefined) {
  try {
    return JSON.parse(fs.readFileSync(path, 'utf8'));
  } catch (err) {
    if (fallback !== undefined) return fallback;
    throw err;
  }
}

export const writeJson = (path, value) => fs.writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`);

// Substitute {{KEY}} tokens from `ids` into any string/JSON. Longer keys first so SALUTATION2
// wins over SALUTATION.
export function fillPlaceholders(text, ids) {
  const keys = Object.keys(ids).sort((a, b) => b.length - a.length);
  let out = String(text);
  for (const key of keys) out = out.split(`{{${key}}}`).join(ids[key] ?? '');
  return out;
}

export const unresolvedPlaceholders = (text) => [...new Set(String(text).match(/\{\{[A-Z0-9_]+\}\}/g) || [])];

// The one result.json shape every executor and the blocked path emit. Callers pass the parts that
// differ (status, assertion, evidence); everything else is boilerplate kept consistent here.
export function makeResult({ plan, target, status, assertion, evidence = {}, blockedReason = null }) {
  return {
    schema_version: '1',
    issue: plan.issue ?? 0,
    target,
    version: plan.version ?? 'unknown',
    executor: plan.executor ?? 'unknown',
    status,
    assertion,
    duration_s: 0,
    evidence: {
      script: '', script_lang: 'sh', reporter_output: '', http: [], artifacts: [], truncated: false,
      ...evidence,
    },
    blocked_reason: blockedReason,
  };
}

// A leg that could not run (bad env, seed failure) is `blocked`, never a fake pass/fail.
export const blockedResult = (plan, target, reason) =>
  makeResult({ plan, target, status: 'blocked', assertion: { expect: null, actual: null, matched: null }, evidence: { reporter_output: reason }, blockedReason: reason });

export function die(message, code = 1) {
  console.error(`repro: ${message}`);
  process.exit(code);
}
