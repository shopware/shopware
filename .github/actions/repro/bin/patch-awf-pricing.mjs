#!/usr/bin/env node
// Post-compile patch for reproduce-analyze.lock.yml.
//
// WHY: gh-aw's firewall api-proxy prices every model for AI-credits accounting from a built-in
// table. claude-sonnet-5 is NOT in gh-aw v0.81.6's table, and gh-aw exposes NO frontmatter knob
// for apiProxy.defaultAiCreditsPricing (confirmed: the model-pricing `models:` frontmatter feeds
// the harness models.json, not the firewall). Without a price the proxy 400s ("Model
// claude-sonnet-5 has no AI credits pricing"). This injects the fallback the error message asks
// for, at the Sonnet rate ($3/$15 per MTok — the "same price" the switch relies on).
//
// Run AFTER `gh aw compile reproduce-analyze`. Idempotent. Remove once a gh-aw release ships
// claude-sonnet-5 pricing (then this patch + the note in reproduce-analyze.md can go).
import fs from 'node:fs';

const LOCK = '.github/workflows/reproduce-analyze.lock.yml';
// Schema requires NUMBERS (strings → "must be a number", invalid config). Per-token unit
// (0.000003 = $3/MTok input, 0.000015 = $15/MTok output — the Sonnet rate); per-MTok numbers
// (3.0) load but the api-proxy rejects them as an implausible per-token price ("not configured").
const INJECT = '"defaultAiCreditsPricing":{"input":0.000003,"output":0.000015},';
const ANCHOR = '"apiProxy":{"enabled":true';

let s = fs.readFileSync(LOCK, 'utf8');
if (s.includes('"defaultAiCreditsPricing"')) { console.log('patch-awf-pricing: already present, nothing to do'); process.exit(0); }
const occurrences = s.split(ANCHOR).length - 1;
if (occurrences === 0) { console.error(`patch-awf-pricing: FAILED — anchor '${ANCHOR}' not found (did the awf-config shape change?)`); process.exit(1); }
s = s.split(ANCHOR).join(`"apiProxy":{${INJECT}"enabled":true`);
fs.writeFileSync(LOCK, s);
console.log(`patch-awf-pricing: injected apiProxy.defaultAiCreditsPricing into ${occurrences} awf-config block(s)`);
