// Remove the optional video-only narration from a spec, leaving the exact reproduction logic that
// the verdict run executes and the issue comment shows. Narration is authored as standalone
// `await narrate(...)` / `await mark(...)` statements plus one `./video-helpers.js` import — all
// whole-line, so the clean spec differs from the authored one ONLY by removed lines (the action and
// assertion code is byte-identical). That equality is what lets the comment be trusted.
import fs from 'node:fs';

export function stripNarration(spec) {
  return String(spec)
    .replace(/^[ \t]*import\s+\{[^}]*\}\s+from\s+['"]\.\/video-helpers\.js['"];?[ \t]*\n/gm, '')
    .replace(/^[ \t]*await\s+(?:narrate|mark)\([\s\S]*?\);[ \t]*\n/gm, '')
    .replace(/\n{3,}/g, '\n\n')
    .trimStart();
}

// True if narration survived a strip (malformed / multi-statement) — the caller should refuse it.
export const hasLeftoverNarration = (stripped) =>
  /\bfrom\s+['"]\.\/video-helpers\.js['"]/.test(stripped) || /\bawait\s+(?:narrate|mark)\(/.test(stripped);

if (import.meta.url === `file://${process.argv[1]}`) {
  const [, , input, output] = process.argv;
  if (!input) { console.error('usage: strip-narration.mjs <input> [output]'); process.exit(2); }
  const out = stripNarration(fs.readFileSync(input, 'utf8'));
  if (output) fs.writeFileSync(output, out); else process.stdout.write(out);
}
