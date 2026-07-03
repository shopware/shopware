// Shared building blocks for the reproduce CLI: the file names that make up a bundle,
// the placeholder vocabulary fixtures may use, and small IO/result helpers. Every other
// cli/ module imports from here, so the bundle contract lives in exactly one place.
import fs from 'node:fs';

/**
 * Names every file that forms the reproduction bundle contract.
 *
 * Agents author the bundle files, while the deterministic pipeline writes result and evidence files
 * around them. Keep new filenames here so executors and report renderers stay aligned.
 *
 * @example
 * const plan = readJson(FILES.plan);
 * writeJson(FILES.result, makeResult({ plan, target, status, assertion, evidence }));
 */
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

/**
 * Lists per-install ids that a portable reproduction may reference through placeholders.
 *
 * Fixtures and HTTP plans must use these names instead of literal UUIDs so the same bundle can run
 * on reported and trunk shops, where countries, payment methods, and sales channels differ.
 *
 * @example
 * const payload = fillPlaceholders(JSON.stringify(fixtures), { COUNTRY: liveCountryId });
 */
export const ENTITY_PLACEHOLDERS = [
  'SC',
  'NAV_CAT',
  'COUNTRY',
  'SALUTATION',
  'SALUTATION2',
  'TAX',
  'CURRENCY',
  'LANGUAGE',
  'SYSTEM_LANGUAGE',
  'CUSTOMER_GROUP',
  'PAYMENT_METHOD',
  'SHIPPING_METHOD',
  'ORDER_STATE_OPEN',
  'ORDER_DELIVERY_STATE_OPEN',
  'ORDER_TRANSACTION_STATE_OPEN',
];

/**
 * Returns the provisioned Shopware base URL without a trailing slash.
 */
export const appUrl = () => (process.env.APP_URL || '').replace(/\/$/, '');

/**
 * Returns the local checkout path for commands that must run inside Shopware.
 */
export const shopDir = () => process.env.SHOP_DIR || 'shop';

/**
 * Returns the Admin username used by harness-owned login and API setup.
 */
export const adminUser = () => process.env.SW_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin';

/**
 * Returns the Admin password used by harness-owned login and API setup.
 */
export const adminPass = () => process.env.SW_ADMIN_PASS ?? process.env.ADMIN_PASS ?? 'shopware';

/**
 * Reads a JSON file with an optional fallback for absent or invalid content.
 *
 * Callers that need strict validation omit the fallback so parse errors surface; preview/report paths
 * can pass a fallback when missing files should degrade gracefully.
 */
export function readJson(path, fallback = undefined) {
  try {
    return JSON.parse(fs.readFileSync(path, 'utf8'));
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
export const writeJson = (path, value) => fs.writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`);

/**
 * Replaces bundle placeholders with ids resolved from the current Shopware leg.
 *
 * Longer keys are replaced first so names such as `SALUTATION2` cannot be partially consumed by
 * `SALUTATION`, which keeps JSON fixture and HTTP request templates portable.
 *
 * @example
 * const body = fillPlaceholders('{"countryId":"{{COUNTRY}}"}', ids);
 */
export function fillPlaceholders(text, ids) {
  const keys = Object.keys(ids).sort((a, b) => b.length - a.length);
  let out = String(text);
  for (const key of keys) {
    out = out.split(`{{${key}}}`).join(ids[key] ?? '');
  }
  return out;
}

/**
 * Lists unresolved `{{PLACEHOLDER}}` tokens left after fixture or request substitution.
 */
export const unresolvedPlaceholders = (text) => [...new Set(String(text).match(/\{\{[A-Z0-9_]+\}\}/g) || [])];

/**
 * Builds the canonical `result.json` shape consumed by verdict and comment rendering.
 *
 * Executors pass only their status, assertion, and evidence; this helper keeps metadata and empty
 * evidence fields consistent between Playwright, HTTP, direct, and blocked setup paths.
 *
 * @example
 * return makeResult({ plan, target, status: 'not_reproduced', assertion, evidence });
 */
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

/**
 * Builds a blocked leg result for setup or environment failures.
 *
 * Use this when the bundle could not run faithfully; blocked results are excluded from pass/fail
 * verdicts so setup problems never look like a reproduced or fixed bug.
 */
export const blockedResult = (plan, target, reason) =>
  makeResult({
    plan,
    target,
    status: 'blocked',
    assertion: { expect: null, actual: null, matched: null },
    evidence: { reporter_output: reason },
    blockedReason: reason,
  });

/**
 * Exits the CLI with a consistent `repro:` error prefix.
 */
export function die(message, code = 1) {
  console.error(`repro: ${message}`);
  process.exit(code);
}
