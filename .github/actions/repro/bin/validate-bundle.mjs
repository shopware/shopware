#!/usr/bin/env node
// Static "hard gate" for a reproduce bundle — refuse structurally-broken plans BEFORE the
// expensive reported‖trunk matrix provisions two shops.
//
// Ported from the CORE, general rules of gweiermann's validate-bundle.mjs
// (shopware/shopware#17724) and adapted to OUR plan contract (analysis.json + fixtures.json +
// repro.spec.ts, per references/SCHEMA.md) and OUR seed.sh placeholder allowlist. The
// bug-specific "cookbook" heuristics from that PR (wishlist/cart/CMS/variant/admin-media issue
// pattern-matching) are intentionally NOT ported: they are high-false-positive and were opt-in
// even there. These rules are deterministic structural/safety checks with ~zero false positives
// against our known-good bundles (#28/#29/#31/#33).
//
// Usage: run from the workspace root where analysis.json/fixtures.json/repro.spec.ts live.
// Exit 0 + "OK" when clean; exit 1 + "REFUSED" + the violation list otherwise.
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (f) => { try { return fs.readFileSync(path.join(root, f), 'utf8'); } catch { return ''; } };
const readJson = (f) => { const t = read(f); if (!t) return null; try { return JSON.parse(t); } catch { return undefined; } };

// The only {{...}} tokens seed.sh resolves (keep in sync with seed.sh).
const ALLOWED_PLACEHOLDERS = new Set(['SC', 'NAV_CAT', 'TAX', 'CURRENCY', 'LANGUAGE', 'SALUTATION', 'SALUTATION2', 'COUNTRY']);
const HEX32 = /^[0-9a-f]{32}$/;
const PLACEHOLDER_ID = /^\{\{[A-Z0-9_]+\}\}$/;
// media file-state fields the DAL write-protects; seeding them makes the sync upsert fail.
const MEDIA_WRITE_PROTECTED = ['path', 'uploadedAt', 'fileSize', 'metaData', 'hasFile', 'url', 'thumbnails'];
// "this bundle isn't finished" markers (ported verbatim — tuned, low false-positive).
const UNFINISHED = /\b(?:not yet|future attempt|has not been|have not been|still lacks?|still missing|missing step|needs? to be (?:rewritten|implemented|created|attached|seeded|added)|todo)\b/i;

const violations = [];
const refuse = (reason) => violations.push(reason);

function done() {
  if (violations.length) {
    console.log(`== validate-bundle: REFUSED (${violations.length}) ==`);
    for (const v of violations) console.log(` - ${v}`);
    process.exit(1);
  }
  console.log('== validate-bundle: OK ==');
  process.exit(0);
}

function stripComments(source) {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .split('\n')
    .map((line) => (/^\s*\/\//.test(line) ? '' : line))
    .join('\n');
}

// Generated Playwright specs must be self-contained, sandbox-safe, and harness-driven.
// Ported from #17724; returns the first violation reason or null.
function hostilePlaywrightSpecReason(source) {
  const executable = stripComments(source);
  const imports = [
    ...executable.matchAll(/^\s*import\s+(?:type\s+)?[\s\S]*?\s+from\s+['"]([^'"]+)['"];?/gm),
    ...executable.matchAll(/^\s*import\s+['"]([^'"]+)['"];?/gm),
  ].map((m) => m[1]);
  const requires = [...executable.matchAll(/\brequire\s*\(\s*['"]([^'"]+)['"]\s*\)/g)].map((m) => m[1]);

  const disallowedImport = [...imports, ...requires].find((s) => s !== '@playwright/test');
  if (disallowedImport) return `Playwright spec imports '${disallowedImport}'; generated specs may only import @playwright/test`;
  if (/\bimport\s*\(/.test(executable)) return 'Playwright spec uses dynamic import(); specs must be static, no runtime module loading';
  if (/\b(?:child_process|node:child_process)\b/.test(executable)) return 'Playwright spec references child_process; specs must not execute shell commands';
  if (/\bprocess\.env\b/.test(executable)) return 'Playwright spec reads process.env; baseURL/storageState come from the harness config and fixture values from seeding — the spec must not read env';
  if (/\b(?:eval|Function)\s*\(/.test(executable)) return 'Playwright spec uses eval/Function; specs must not execute generated code';
  if (/\b(?:writeFile(?:Sync)?|appendFile(?:Sync)?|createWriteStream|mkdir(?:Sync)?|rm(?:Sync)?|unlink(?:Sync)?|rmdir(?:Sync)?|openSync)\s*\(/.test(executable))
    return 'Playwright spec writes through Node filesystem APIs; specs may only produce files via browser/filechooser flows or Playwright screenshots';
  const remoteUrl = executable.match(/https?:\/\/(?!127\.0\.0\.1(?::|\/|['"`])|localhost(?::|\/|['"`])|host\.docker\.internal(?::|\/|['"`]))[^\s'"`)]+/i)?.[0];
  if (remoteUrl) return `Playwright spec references non-local network URL '${remoteUrl}'; specs may only drive the provisioned local Shopware instance`;
  if (/\b[A-Za-z_$][\w$]*\.goto\s*\(\s*['"`]https?:\/\/(?:127\.0\.0\.1|localhost|host\.docker\.internal)(?::\d+)?\//.test(executable))
    return 'Playwright spec hardcodes a local absolute navigation URL; use a relative page.goto() path so the harness baseURL controls the host';
  return null;
}

// ---- structural: the plan must parse ----
const analysis = readJson('analysis.json');
if (analysis === null) { console.log('== validate-bundle: REFUSED — analysis.json is missing =='); process.exit(1); }
if (analysis === undefined || typeof analysis !== 'object') { console.log('== validate-bundle: REFUSED — analysis.json is not valid JSON =='); process.exit(1); }
// needs_info / noop plans have no executor and are not executed — nothing to gate.
if (analysis.needs_info) { console.log('== validate-bundle: OK (needs_info, no bundle) =='); process.exit(0); }

// ---- executor + script_path consistency ----
const executor = String(analysis.executor ?? '');
if (!['direct', 'http', 'playwright'].includes(executor)) {
  refuse(`analysis.json executor must be one of direct|http|playwright (got '${executor || '(empty)'}')`);
} else if (executor !== 'http') {
  const expected = executor === 'playwright' ? 'repro.spec.ts' : 'ReproTest.php';
  const scriptPath = String(analysis.script_path ?? '');
  if (!scriptPath) refuse(`executor '${executor}' requires script_path (expected ${expected})`);
  else if (!read(scriptPath)) refuse(`script_path '${scriptPath}' is declared but the file is missing or empty`);
}

// ---- 1. unfinished / stub plan ----
if (UNFINISHED.test(JSON.stringify(analysis.scenario ?? ''))) {
  refuse('analysis.json scenario reads as unfinished (todo / "not yet" / "needs to be …"); ship a complete plan or emit needs_info');
}

// ---- 2. Playwright spec hygiene ----
if (executor === 'playwright') {
  const spec = read(String(analysis.script_path ?? 'repro.spec.ts'));
  if (spec) {
    const reason = hostilePlaywrightSpecReason(spec);
    if (reason) refuse(reason);
    if (UNFINISHED.test(stripComments(spec))) refuse('repro.spec.ts contains an unfinished/stub marker (todo / "not yet" / "needs to be …")');
  }
}

// ---- 3 & 4. fixtures: envelope, placeholders, ids, media write-protected fields ----
const fixtures = readJson('fixtures.json');
if (fixtures && typeof fixtures === 'object' && !Array.isArray(fixtures)) {
  for (const m of JSON.stringify(fixtures).matchAll(/\{\{\s*([A-Z0-9_]+)\s*\}\}/g)) {
    if (!ALLOWED_PLACEHOLDERS.has(m[1])) {
      refuse(`fixtures.json uses unknown placeholder {{${m[1]}}}; seed.sh only resolves ${[...ALLOWED_PLACEHOLDERS].map((p) => `{{${p}}}`).join(', ')}`);
    }
  }
  for (const [key, op] of Object.entries(fixtures)) {
    if (!op || typeof op !== 'object' || Array.isArray(op)) {
      refuse(`fixtures.json['${key}'] must be a sync operation object {entity, action, payload:[…]}, not ${Array.isArray(op) ? 'a bare array' : typeof op}`);
      continue;
    }
    if (typeof op.entity !== 'string' || !op.entity) refuse(`fixtures.json['${key}'] is missing a string 'entity'`);
    if (!Array.isArray(op.payload)) { refuse(`fixtures.json['${key}'] (entity '${op.entity ?? '?'}') is missing a 'payload' array`); continue; }
    for (const row of op.payload) {
      if (!row || typeof row !== 'object') continue;
      if ('id' in row) {
        const id = row.id;
        if (!(typeof id === 'string' && (HEX32.test(id) || PLACEHOLDER_ID.test(id)))) {
          refuse(`fixtures.json '${op.entity}' row has id '${id}'; entity ids must be 32-char lowercase-hex Shopware UUIDs (or a known {{placeholder}})`);
        }
      }
      if (op.entity === 'media') {
        const bad = MEDIA_WRITE_PROTECTED.filter((f) => f in row);
        if (bad.length) refuse(`fixtures.json 'media' row sets write-protected field(s) ${bad.join(', ')}; seed media metadata only — uploaded bytes come from the runtime upload call`);
      }
    }
  }
} else if (fixtures === undefined) {
  refuse('fixtures.json exists but is not valid JSON');
}

done();
