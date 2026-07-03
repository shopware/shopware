// Remove the optional video-only narration from a spec, leaving the exact reproduction logic that
// the verdict run executes and the issue comment shows. Narration is authored as standalone
// `await narrate(...)` / `await mark(...)` statements plus one `./video-helpers.js` import — all
// whole-line, so the clean spec differs from the authored one ONLY by removed lines (the action and
// assertion code is byte-identical). That equality is what lets the comment be trusted.
import fs from 'node:fs';

/**
 * Removes video-only narration from an authored Playwright reproduction spec.
 *
 * The verdict run and issue comment use this stripped source so subtitles and marker helpers cannot
 * affect the executed actions or final assertion.
 */
export function stripNarration(spec) {
  return String(spec)
    .replace(/^[ \t]*import\s+\{[^}]*\}\s+from\s+['"]\.\/video-helpers\.js['"];?[ \t]*\n/gm, '')
    .replace(/^[ \t]*await\s+(?:narrate|mark)\([\s\S]*?\);[ \t]*\n/gm, '')
    .replace(/\n{3,}/g, '\n\n')
    .trimStart();
}

/**
 * Detects malformed narration that survived stripping.
 *
 * Callers refuse the bundle when this returns true because hidden narration statements could change
 * the verdict run or make the displayed spec differ from the executed one.
 */
export const hasLeftoverNarration = (stripped) =>
  /\bfrom\s+['"]\.\/video-helpers\.js['"]/.test(stripped) || /\bawait\s+(?:narrate|mark)\(/.test(stripped);

if (import.meta.url === `file://${process.argv[1]}`) {
  const [, , input, output] = process.argv;
  if (!input) {
    console.error('usage: strip-narration.mjs <input> [output]');
    process.exit(2);
  }
  const out = stripNarration(fs.readFileSync(input, 'utf8'));
  if (output) {
    fs.writeFileSync(output, out);
  } else {
    process.stdout.write(out);
  }
}
