/**
 * Shared bundle contract and small helpers for the deterministic reproduce CLI.
 *
 * File names, placeholder vocabulary, IO helpers, and result construction live here so commands,
 * full-run orchestration, and executors agree on one authored-bundle shape.
 */
import fs from 'node:fs';
import type { Plan, LegResult, Evidence } from './types.ts';

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

// Core ids stable across every Shopware install — the only bare 32-hex ids allowed in an HTTP plan
// (validate.ts) and as fixture references (seed.ts). Single source so the two can't drift.
export const STABLE_IDS = new Set([
  '2fbb5fe2e29a4d70aa5854ce7ce3e20b', // Defaults::LANGUAGE_SYSTEM
  'b7d2554b0ce847cd82f3ac9bd1c0dfca', // Defaults::LIVE_VERSION
]);

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

export const RUNTIME_PLACEHOLDERS = [
  'STOREFRONT_URL',
  'SW_ACCESS_KEY',
  'SW_CONTEXT_TOKEN',
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
export function readJson<T = unknown>(path: string, fallback?: T): T {
  try {
    return JSON.parse(fs.readFileSync(path, 'utf8')) as T;
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
export const writeJson = (path: string, value: unknown): void =>
  fs.writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`);

/**
 * Replaces bundle placeholders with ids resolved from the current Shopware leg.
 *
 * Longer keys are replaced first so names such as `SALUTATION2` cannot be partially consumed by
 * `SALUTATION`, which keeps JSON fixture and HTTP request templates portable.
 *
 * @example
 * const body = fillPlaceholders({ countryId: '{{COUNTRY}}' }, ids);
 */
export function fillPlaceholders(value: unknown, ids: Record<string, string | undefined | null>): string {
  const keys = Object.keys(ids).sort((a, b) => b.length - a.length);
  let out = '';

  if (value !== null && value !== undefined && value !== false) {
    out = typeof value === 'object' ? JSON.stringify(value) : String(value);
  }

  for (const key of keys) {
    const value_ = ids[key];
    // An EMPTY resolution (e.g. firstId() on a search that returned no rows) is NOT a valid
    // substitution — substituting '' would erase the {{KEY}} token and slip a malformed request
    // (empty UUID) past unresolvedPlaceholders. Leave the token instead so the leg blocks with a
    // clear "unresolved placeholder" reason rather than silently diverging on install differences.
    if (value_ === undefined || value_ === null || value_ === '') {
      continue;
    }
    out = out.split(`{{${key}}}`).join(value_);
  }
  return out;
}

/**
 * Lists unresolved `{{PLACEHOLDER}}` tokens left after fixture or request substitution.
 */
export const unresolvedPlaceholders = (text: unknown): string[] =>
  [...new Set(String(text).match(/\{\{[A-Z0-9_]+\}\}/g) || [])];

/**
 * Lists placeholder names referenced by bundle fragments.
 */
export const referencedPlaceholders = (...values: unknown[]): string[] =>
  unresolvedPlaceholders(JSON.stringify(values)).map((placeholder) => placeholder.slice(2, -2));

/**
 * Builds the canonical `result.json` shape consumed by verdict and comment rendering.
 *
 * Executors pass only their status, assertion, and evidence; this helper keeps metadata and empty
 * evidence fields consistent between Playwright, HTTP, direct, and blocked setup paths.
 *
 * @example
 * return makeResult({ plan, target, status: 'not_reproduced', assertion, evidence });
 */
export function makeResult({ plan, target, status, assertion, evidence = {}, blockedReason = null }: {
  plan: Partial<Plan>;
  target: string;
  status: LegResult['status'];
  assertion: LegResult['assertion'];
  evidence?: Partial<Evidence>;
  blockedReason?: string | null;
}): LegResult {
  return {
    schema_version: '1',
    issue: plan.issue ?? 0,
    target,
    // The version the workflow actually resolved + provisioned wins over the agent-authored
    // plan.version (which is untrusted and can be wrong). The trusted verify steps pass it via
    // REPRO_RESOLVED_VERSION; fall back to plan.version only outside that path (e.g. local dev).
    version: process.env.REPRO_RESOLVED_VERSION || plan.version || 'unknown',
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
export const blockedResult = (plan: Partial<Plan>, target: string, reason: string): LegResult =>
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
export function die(message: string, code = 1): never {
  console.error(`repro: ${message}`);
  process.exit(code);
}
