#!/usr/bin/env node
/// <reference types="node" />
// biome-ignore lint/style/useNodejsImportProtocol: @types/node@12 doesn't support node: protocol
import { execSync } from 'child_process';

/**
 * npm-audit-gate
 *
 * - Runs `npm audit --json`
 * - Filters out explicitly ignored advisories (stable match by GHSA/URL, optional numeric source)
 * - Prunes transitive "via" references that only point to filtered/cleared vulns
 * - Fails CI if anything remains
 * - Retries on transient network/DNS errors
 */

// ============================================================================
// Types
// ============================================================================

interface VulnerabilityAdvisory {
  source?: number;
  title?: string;
  url?: string;
  severity?: string;
  id?: string;
}

type ViaEntry = string | VulnerabilityAdvisory;

interface Vulnerability {
  name?: string;
  severity?: string;
  range?: string;
  via: ViaEntry[];
}

interface LegacyAdvisory {
  module_name?: string;
  title?: string;
  url?: string;
  severity?: string;
}

interface AuditError {
  code: string;
  summary: string;
  detail?: string;
}

interface AuditResult {
  vulnerabilities?: Record<string, Vulnerability>;
  advisories?: Record<string, LegacyAdvisory>;
  error?: AuditError;
}

interface IgnorePolicy {
  sources: Set<number>;
  ghsa: Set<string>;
  urls: Set<string>;
  titleIncludes: string[];
}

interface ExecErrorLike {
  stdout?: Buffer | string;
  stderr?: Buffer | string;
  message?: string;
}

// ============================================================================
// Configuration
// ============================================================================

/**
 * Ignore policy - prefer GHSA or URL (stable across npm versions)
 * Numeric source IDs are less stable but can be used as fallback
 */
const IGNORE: IgnorePolicy = {
  sources: new Set<number>([
    1112686, // ESLint, moderate severity, major update necessary
  ]),
  ghsa: new Set<string>([
    'GHSA-p5wg-g6qr-c7cg', // eslint circular refs stack overflow
  ]),
  urls: new Set<string>([
    'https://github.com/advisories/GHSA-p5wg-g6qr-c7cg',
  ]),
  titleIncludes: [
    // 'eslint has a Stack Overflow', // example: match by title substring (less stable)
  ],
};

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 1500;

// ============================================================================
// Type Guards
// ============================================================================

function isViaObject(v: ViaEntry): v is VulnerabilityAdvisory {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

function isExecError(err: unknown): err is ExecErrorLike {
  return typeof err === 'object' && err !== null;
}

function isError(err: unknown): err is Error {
  return err instanceof Error;
}

// ============================================================================
// Utility Functions
// ============================================================================

function sleepSync(ms: number): void {
  const sab = new SharedArrayBuffer(4);
  const ia = new Int32Array(sab);
  Atomics.wait(ia, 0, 0, ms);
}

function isTransientNetworkError(text: string): boolean {
  const needles = [
    'EAI_AGAIN',
    'ENOTFOUND',
    'ECONNRESET',
    'ETIMEDOUT',
    'ECONNREFUSED',
    'socket hang up',
    'network timeout',
    'audit endpoint returned an error',
    'FetchError:',
    'getaddrinfo',
    'registry.npmjs.org',
  ];
  return needles.some((needle) => text.includes(needle));
}

function extractGhsa(v: VulnerabilityAdvisory): string | null {
  // npm often provides v.url like https://github.com/advisories/GHSA-xxxx-xxxx-xxxx
  if (typeof v.url === 'string') {
    const match = v.url.match(/GHSA-[a-z0-9-]{4,}/i);
    if (match) return match[0];
  }
  // Sometimes data contains an explicit id field
  if (typeof v.id === 'string' && v.id.startsWith('GHSA-')) {
    return v.id;
  }
  return null;
}

// ============================================================================
// Ignore Logic
// ============================================================================

function isIgnoredVuln(v: VulnerabilityAdvisory): boolean {
  // Match by URL (most stable)
  if (typeof v.url === 'string' && IGNORE.urls.has(v.url)) {
    return true;
  }

  // Match by GHSA ID (also stable)
  const ghsa = extractGhsa(v);
  if (ghsa && IGNORE.ghsa.has(ghsa)) {
    return true;
  }

  // Match by numeric source (less stable, may be missing)
  if (typeof v.source === 'number' && IGNORE.sources.has(v.source)) {
    return true;
  }

  // Match by title substring (fallback, least stable)
  if (typeof v.title === 'string' && IGNORE.titleIncludes.length > 0) {
    const titleLower = v.title.toLowerCase();
    if (IGNORE.titleIncludes.some((s) => titleLower.includes(s.toLowerCase()))) {
      return true;
    }
  }

  return false;
}

// ============================================================================
// Audit Parsing
// ============================================================================

function normalizeAuditJson(raw: string): AuditResult & { vulnerabilities: Record<string, Vulnerability> } {
  const audit = JSON.parse(raw) as AuditResult;

  // Handle npm error responses (e.g., no lockfile, network errors)
  if (audit.error) {
    const errorMsg = audit.error.summary ?? audit.error.code ?? 'Unknown npm audit error';
    const detail = audit.error.detail ? `\n${audit.error.detail}` : '';
    const hint = audit.error.code === 'ENOLOCK'
      ? '\n\nHint: Run this script from a directory containing package-lock.json (e.g., src/Storefront/Resources/app/storefront/)'
      : '';
    throw new Error(`npm audit error: ${errorMsg}${detail}${hint}`);
  }

  // npm v7+ format: audit.vulnerabilities exists
  if (audit.vulnerabilities && typeof audit.vulnerabilities === 'object') {
    return audit as AuditResult & { vulnerabilities: Record<string, Vulnerability> };
  }

  // Legacy format (older npm): audit.advisories exists
  if (audit.advisories && typeof audit.advisories === 'object') {
    const vulnerabilities: Record<string, Vulnerability> = {};

    for (const id of Object.keys(audit.advisories)) {
      const adv = audit.advisories[id];
      if (!adv) continue;

      const name = adv.module_name ?? `advisory-${id}`;
      vulnerabilities[name] ??= { name, severity: adv.severity, via: [] };
      vulnerabilities[name].via.push({
        source: Number(id),
        title: adv.title,
        url: adv.url,
        severity: adv.severity,
      });
    }

    return { ...audit, vulnerabilities };
  }

  throw new Error('Unrecognized `npm audit --json` format (no vulnerabilities/advisories).');
}

// ============================================================================
// npm audit Execution with Retries
// ============================================================================

function runNpmAuditJsonWithRetries(): string {
  let lastError: unknown = null;

  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    try {
      const output = execSync('npm audit --json', {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
      });
      return output;
    } catch (err: unknown) {
      lastError = err;

      if (!isExecError(err)) {
        console.error('❌ Unexpected error running npm audit.');
        process.exit(2);
      }

      const stdout = err.stdout?.toString() ?? '';
      const stderr = err.stderr?.toString() ?? '';
      const message = err.message ?? '';

      // If stdout contains JSON, npm exited 1 due to vulnerabilities (not an error)
      if (stdout.trim().startsWith('{')) {
        return stdout;
      }

      // Check if this is a transient network error
      const combined = `${message}\n${stderr}\n${stdout}`;
      if (attempt < MAX_RETRIES && isTransientNetworkError(combined)) {
        console.error(`⚠️  npm audit failed (network/DNS), attempt ${attempt}/${MAX_RETRIES}. Retrying...`);
        sleepSync(RETRY_DELAY_MS * attempt);
        continue;
      }

      // Hard failure
      console.error('❌ Error running npm audit.');
      if (stderr) console.error(stderr.trim());
      if (message) console.error(message.trim());
      process.exit(2);
    }
  }

  // Retries exhausted
  console.error('❌ npm audit failed and retries were exhausted.');
  if (isError(lastError)) {
    console.error(lastError.message);
  }
  process.exit(2);
}

// ============================================================================
// Vulnerability Analysis
// ============================================================================

function hasRemainingVulnObject(pkg: Vulnerability | undefined): boolean {
  return Array.isArray(pkg?.via) && pkg.via.some((v) => isViaObject(v));
}

function filterIgnoredVulnerabilities(audit: { vulnerabilities: Record<string, Vulnerability> }): void {
  // First pass: filter ignored vulnerability objects out of `via`
  for (const pkgName of Object.keys(audit.vulnerabilities)) {
    const pkg = audit.vulnerabilities[pkgName];
    if (!pkg || !Array.isArray(pkg.via)) continue;

    pkg.via = pkg.via.filter((v) => {
      // Keep strings (dependency references) for now
      if (typeof v === 'string') return true;
      // Keep vuln objects unless ignored
      if (isViaObject(v)) return !isIgnoredVuln(v);
      // Unknown types: keep (safer)
      return true;
    });
  }

  // Second pass: prune string references pointing to packages with no remaining vuln objects
  let changed = true;
  while (changed) {
    changed = false;

    for (const pkgName of Object.keys(audit.vulnerabilities)) {
      const pkg = audit.vulnerabilities[pkgName];
      if (!pkg || !Array.isArray(pkg.via) || pkg.via.length === 0) continue;

      const before = pkg.via.length;

      pkg.via = pkg.via.filter((v) => {
        if (typeof v !== 'string') return true;

        const refPkg = audit.vulnerabilities[v];
        // Keep the reference only if the referenced package still has real vuln objects
        return hasRemainingVulnObject(refPkg);
      });

      if (pkg.via.length !== before) {
        changed = true;
      }
    }
  }
}

function reportVulnerabilities(vulnerabilities: Record<string, Vulnerability>): void {
  const remainingPkgs = Object.entries(vulnerabilities).filter(([, pkg]) =>
    hasRemainingVulnObject(pkg)
  );

  if (remainingPkgs.length === 0) {
    console.log('✅ No vulnerabilities (ignored advisories excluded).');
    return;
  }

  console.error(`❌ Remaining vulnerabilities detected: ${remainingPkgs.length}\n`);

  for (const [key, pkg] of remainingPkgs) {
    const name = pkg.name ?? key;
    console.error(`Package: ${name}`);
    if (pkg.severity) console.error(`Severity: ${pkg.severity}`);
    if (pkg.range) console.error(`Range: ${pkg.range}`);

    for (const v of pkg.via) {
      if (typeof v === 'string') {
        console.error(`  - Dependency issue: ${v}`);
        continue;
      }

      if (!isViaObject(v)) continue;

      const title = v.title ?? 'Unknown vulnerability';
      const severity = v.severity ?? 'unknown';
      const ghsa = extractGhsa(v);
      const url = v.url ?? null;

      console.error(`  - ${title}`);
      console.error(`    Severity: ${severity}`);
      if (ghsa) console.error(`    GHSA: ${ghsa}`);
      if (url) console.error(`    URL: ${url}`);
    }

    console.error('');
  }

  process.exit(1);
}

// ============================================================================
// Main
// ============================================================================

function main(): void {
  const auditRaw = runNpmAuditJsonWithRetries();

  let audit: { vulnerabilities: Record<string, Vulnerability> };
  try {
    audit = normalizeAuditJson(auditRaw);
  } catch (err: unknown) {
    const message = isError(err) ? err.message : String(err);
    console.error('❌ Failed to parse/normalize npm audit JSON:', message);
    process.exit(2);
  }

  filterIgnoredVulnerabilities(audit);
  reportVulnerabilities(audit.vulnerabilities);
}

main();
