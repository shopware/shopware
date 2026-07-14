#!/usr/bin/env node
/**
 * Renders the issue comment: a harness-owned FRAME around the agent-authored report BODY.
 *
 * The frame (verdict, legs table, audit callouts, footer) comes from templates/ and cannot be
 * influenced by the agent. The body is the agent's `repro/comment.md` with its `{{…}}` placeholders
 * resolved from TRUSTED artifacts only — authored files, the trusted legs' results, and published
 * evidence — so the agent references facts but can never author them. Files the agent changed but
 * never referenced are called out in the frame (disclosure by default).
 *
 *   verdict comment:  VERDICT, UNSURE, RUN_URL, ART(=artifacts)  → comment.md
 *   incomplete:       MODE=incomplete, REASON, RUN_URL            → comment.md
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { readJson, redact } from '../lib.mjs';

const templates = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'templates');
const DATA = JSON.parse(fs.readFileSync(path.join(templates, 'verdicts.json'), 'utf8'));
const OUT_FILE = process.env.OUT || 'comment.md';
const MAX_COMMENT_CHARS = 60000; // GitHub caps comments at 65536; leave frame headroom

const LANG_BY_EXT = {
  sh: 'bash', bash: 'bash', ts: 'ts', mts: 'ts', js: 'js', mjs: 'js', php: 'php', json: 'json',
  twig: 'twig', html: 'html', xml: 'xml', sql: 'sql', yml: 'yaml', yaml: 'yaml', md: 'markdown',
};
const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

/**
 * Renders the tiny frame template language: `{{#KEY}}…{{/KEY}}` sections, then one variable pass
 * (substituted values are never re-scanned, so agent-controlled text cannot inject template syntax).
 */
function renderFrame(tpl, ctx) {
  let out = tpl;
  let prev;
  const sectionPattern = /{{#(\w+)}}\n?([\s\S]*?){{\/\1}}\n?/g;
  do {
    prev = out;
    out = out.replace(sectionPattern, (_, key, inner) => (ctx[key] ? inner : ''));
  } while (out !== prev);
  return out.replace(/{{(\w+)}}/g, (_, key) => ctx[key] ?? '');
}

/**
 * Fences content safely: the fence is always longer than any backtick run inside the content.
 */
function fence(content, lang = '') {
  const longest = Math.max(2, ...[...String(content).matchAll(/`+/g)].map((m) => m[0].length));
  const marks = '`'.repeat(longest + 1);
  return `${marks}${lang}\n${String(content).replace(/\n$/, '')}\n${marks}`;
}

/**
 * Renders a code block, collapsing it into a spoiler when it is long.
 */
function codeBlock(content, lang, summary) {
  const block = fence(content, lang);
  const long = String(content).split('\n').length > 40 || String(content).length > 3000;
  return long ? `<details><summary>${summary}</summary>\n\n${block}\n\n</details>` : block;
}

/**
 * Extracts the file paths a template references via `{{file:…}}` — the agent's disclosure set.
 */
export const referencedFiles = (template) => [...String(template).matchAll(/{{file:([^}]+)}}/g)]
  .map((m) => m[1].trim().replace(/^\.\//, ''));

/**
 * Builds the placeholder resolver over trusted sources.
 *
 * @param legs        {reported?: object, trunk?: object} — trusted result.json per leg.
 * @param evidence    evidence.json manifest ({legs: [{name, files: [{file, url, caption}]}]}), or null.
 * @param readFile    (path) => string|null — reads an AUTHORED file from the trusted bundle copy.
 */
export function createResolver({ legs, evidence, readFile }) {
  const leg = (name) => legs[name] || null;
  const listOrNone = (values, none) => (values?.length ? values.join('; ') : none);

  const runField = (legName, field) => {
    const l = leg(legName);
    if (!l) {
      return `_(the ${legName} leg did not run)_`;
    }
    switch (field) {
      case 'output':
        return l.log_tail
          ? `<details><summary>run.sh output (${legName})</summary>\n\n${fence(l.log_tail)}\n\n</details>`
          : '_(no output)_';
      case 'exit':
        return String(l.exit_code ?? '?');
      case 'status':
        return DATA.phrases.status[l.status] || l.status;
      case 'observed':
        return listOrNone(l.observed, '_(nothing reported via `##repro observed`)_');
      case 'expected':
        return listOrNone(l.expected, '_(nothing reported via `##repro expected`)_');
      case 'steps':
        return l.steps?.length ? l.steps.map((s) => `1. ${s}`).join('\n') : '_(no steps narrated)_';
      case 'shop_changes':
        return l.shop_changes?.length ? fence(l.shop_changes.join('\n')) : '_(no changes inside the shop)_';
      default:
        return null;
    }
  };

  const evidenceRef = (legName, file) => {
    const published = (evidence?.legs || []).find((l) => l.name === legName)?.files
      ?.find((f) => f.file === file);
    if (published) {
      const caption = published.caption || file;
      return IMAGE_EXT.includes(file.split('.').pop().toLowerCase())
        ? `![${caption}](${published.url})`
        : `[${caption}](${published.url})`;
    }
    const recorded = leg(legName)?.evidence?.some((e) => e.file === file);
    return recorded ? `_(\`${file}\` was recorded but not published)_` : `_(not produced on the ${legName} leg)_`;
  };

  return (token) => {
    const [ns, ...rest] = token.split(':').map((s) => s.trim());
    if (ns === 'file') {
      const p = rest.join(':').replace(/^\.\//, '');
      const content = readFile(p);
      return content === null
        ? `_(\`${p}\` is not part of the bundle — keep authored files inside \`repro/\`)_`
        : codeBlock(content, LANG_BY_EXT[p.split('.').pop()] || '', `<code>${p}</code>`);
    }
    if (ns === 'run' && rest.length === 2) {
      return runField(rest[0], rest[1]);
    }
    if (ns === 'evidence' && rest.length === 2) {
      return evidenceRef(rest[0], rest[1]);
    }
    if (ns === 'diff' && rest[0] === 'shop') {
      return runField(legs.reported ? 'reported' : 'trunk', 'shop_changes');
    }
    return null; // unknown namespace — leave the literal text alone
  };
}

/**
 * Renders the agent's body template: strips its instruction comments, then resolves every
 * `{{namespace:…}}` placeholder through the trusted resolver. Known-namespace tokens that resolve
 * to nothing render as an explicit marker instead of vanishing — a gap is itself signal.
 */
export function renderBody(template, resolver) {
  return String(template)
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/{{\s*((?:file|run|evidence|diff):[^}]*)}}/g, (whole, token) => {
      const resolved = resolver(token);
      return resolved === null ? '_(not produced)_' : resolved;
    })
    .trim();
}

/**
 * Normalizes spacing and enforces the GitHub comment size cap at the very end.
 */
function finalize(markdown) {
  let out = `${markdown.replace(/\n{3,}/g, '\n\n').trim()}\n`;
  if (out.length > MAX_COMMENT_CHARS) {
    out = `${out.slice(0, MAX_COMMENT_CHARS)}\n\n… _(comment truncated — see the workflow run for full artifacts)_\n`;
  }
  return redact(out);
}

/**
 * Reads an auxiliary artifact from the bundle artifact dir, then the working directory.
 */
function readExtra(name) {
  for (const p of [`${process.env.ART || 'artifacts'}/repro-bundle/${name}`, name]) {
    try {
      const text = fs.readFileSync(p, 'utf8').trim();
      if (text) {
        return text;
      }
    } catch {
      // Try the next candidate location.
    }
  }
  return '';
}

/**
 * Assembles and writes the full comment for the report job.
 */
function main() {
  const art = process.env.ART || 'artifacts';
  const bundleDir = `${art}/repro-bundle`;
  const runContext = readJson(`${bundleDir}/run-context.json`, {});

  if (process.env.MODE === 'incomplete') {
    const tpl = fs.readFileSync(path.join(templates, 'frame.incomplete.md'), 'utf8');
    write(renderFrame(tpl, {
      REASON: readExtra('giveup.txt') || process.env.REASON || DATA.phrases.incomplete_reason_default,
      AGENT_SUMMARY: readExtra('agent-summary.md'),
      RUN_SH: (() => {
        try {
          return fs.readFileSync(`${bundleDir}/repro/run.sh`, 'utf8').trim();
        } catch {
          return '';
        }
      })(),
      UNDISCLOSED: readExtra('files-changed.txt'),
      RUN_URL: process.env.RUN_URL || '',
    }));
    return;
  }

  const legs = {
    reported: readJson(`${art}/repro-reported/result.json`, null),
    trunk: readJson(`${art}/repro-trunk/result.json`, null),
  };
  const resolver = createResolver({
    legs,
    evidence: readJson('evidence.json', null),
    readFile: (p) => {
      try {
        return fs.readFileSync(path.join(bundleDir, p), 'utf8');
      } catch {
        return null;
      }
    },
  });

  let bodyTemplate = '';
  try {
    bodyTemplate = fs.readFileSync(`${bundleDir}/repro/comment.md`, 'utf8');
  } catch {
    // Missing template → the fallback body below still reports the run.
  }
  const body = bodyTemplate
    ? renderBody(bodyTemplate, resolver)
    : [
      '_The agent did not author a report template; this is the harness fallback._',
      renderBody('{{file:repro/run.sh}}\n\n**Reported version:** {{run:reported:output}}\n\n**Trunk:** {{run:trunk:output}}', resolver),
    ].join('\n\n');

  const changed = readExtra('files-changed.txt').split('\n').filter(Boolean);
  const disclosed = new Set(referencedFiles(bodyTemplate));
  const undisclosed = changed.filter((p) => !disclosed.has(p));
  const inconsistencies = [...(legs.reported?.inconsistencies || []), ...(legs.trunk?.inconsistencies || [])];

  const verdict = process.env.VERDICT || 'needs_human_review';
  const entry = DATA.verdicts[verdict] || { headline: verdict, badge: verdict, callout: '' };
  const legStatus = (l) => DATA.phrases.status[l?.status ?? 'null'] || DATA.phrases.status.null;

  const tpl = fs.readFileSync(path.join(templates, 'frame.md'), 'utf8');
  write(renderFrame(tpl, {
    HEADLINE: entry.headline,
    VERDICT_BADGE: entry.badge,
    RV: runContext.version || '?',
    REPORTED_STATUS: legStatus(legs.reported),
    TRUNK_STATUS: legStatus(legs.trunk),
    DATE: process.env.DATE || new Date().toISOString().slice(0, 10),
    UNSURE: process.env.UNSURE || '',
    CALLOUT: entry.callout,
    SHOP_EDITS: readExtra('shop-src-edits.txt').replace(/\n/g, '\n> '),
    SHOP_EDITS_INTRO: DATA.phrases.shop_edits_intro,
    UNDISCLOSED: undisclosed.join('\n> '),
    UNDISCLOSED_INTRO: DATA.phrases.undisclosed_intro,
    INCONSISTENCIES: inconsistencies.map((i) => `> - ${i}`).join('\n'),
    INCONSISTENCY_INTRO: DATA.phrases.inconsistency_intro,
    BODY: body,
    AGENT_SUMMARY: readExtra('agent-summary.md'),
    FOOTER: DATA.phrases.footer,
    RUN_URL: process.env.RUN_URL || '',
  }));
}

/**
 * Writes the final comment markdown to disk, step summary, and stdout.
 */
function write(markdown) {
  const done = finalize(markdown);
  fs.writeFileSync(OUT_FILE, done);
  if (process.env.GITHUB_STEP_SUMMARY) {
    fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, done);
  }
  process.stdout.write(done);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
