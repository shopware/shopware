/**
 * Playwright narration stripper used by validation and the executor's verdict run.
 *
 * Narration is authored as standalone `await narrate(...)` / `await mark(...)` statements plus one
 * `./video-helpers.js` import. Removing only those lines keeps the action and assertion code
 * byte-identical between the authored video spec and the machine-verdict spec.
 */
import fs from 'node:fs';

/**
 * Removes video-only narration from an authored Playwright reproduction spec.
 *
 * The verdict run and issue comment use this stripped source so subtitles and marker helpers cannot
 * affect the executed actions or final assertion.
 */
export function stripNarration(spec: string): string {
  const withoutImport = String(spec).replace(
    /^[ \t]*import\s+\{[^}]*\}\s+from\s+['"]\.\/video-helpers\.js['"];?[ \t]*\n/gm,
    '',
  );
  return removeNarrationStatements(withoutImport).replace(/\n{3,}/g, '\n\n').trimStart();
}

/**
 * Removes standalone narration statements without swallowing adjacent Playwright actions.
 *
 * A regex span across lines is unsafe because malformed narration could match through the next
 * `);`. This scanner consumes exactly the balanced argument list while respecting string and
 * template delimiters.
 */
function removeNarrationStatements(source: string): string {
  const start = /(^|\n)([ \t]*)await[ \t]+(?:narrate|mark)[ \t]*\(/g;
  let out = '';
  let cursor = 0;
  let match;
  while ((match = start.exec(source)) !== null) {
    const stmtStart = match.index + match[1].length; // position of leading indentation
    const openParen = match.index + match[0].length - 1; // position of the '('
    const end = scanStatementEnd(source, openParen);
    if (end === -1) {
      // Unbalanced/parse-defeating call — leave it in place so hasLeftoverNarration() can flag it
      // rather than risk over-stripping.
      continue;
    }
    // Emit everything up to the statement, then skip the statement (and its trailing newline).
    out += source.slice(cursor, stmtStart);
    let after = end;
    if (source.slice(after).startsWith('\r\n')) after += 2;
    else if (source[after] === '\n' || source[after] === '\r') after += 1;
    cursor = after;
    start.lastIndex = after;
  }
  out += source.slice(cursor);
  return out;
}

/**
 * Finds the end of a standalone narration call.
 *
 * Returns `-1` when parentheses never balance or the line contains unexpected trailing code, because
 * stripping that statement could remove real reproduction logic.
 */
function scanStatementEnd(source: string, openParen: number): number {
  let depth = 0;
  let i = openParen;
  let quote = null; // active string/template delimiter
  for (; i < source.length; i++) {
    const ch = source[i];
    if (quote) {
      if (ch === '\\') {
        i++; // skip escaped char
      } else if (ch === quote) {
        quote = null;
      }
      continue;
    }
    if (ch === "'" || ch === '"' || ch === '`') {
      quote = ch;
      continue;
    }
    if (ch === '(') {
      depth++;
    } else if (ch === ')') {
      depth--;
      if (depth === 0) {
        i++; // move past the closing ')'
        // Allow an optional trailing ';' and horizontal whitespace, then require end-of-line/input.
        if (source[i] === ';') i++;
        while (source[i] === ' ' || source[i] === '\t') i++;
        if (i >= source.length || source[i] === '\n' || source[i] === '\r') {
          return i;
        }
        // A trailing `// ...` line comment is still just narration formatting (it cannot contain
        // executable code), so consume it up to the end of the line.
        if (source[i] === '/' && source[i + 1] === '/') {
          while (i < source.length && source[i] !== '\n' && source[i] !== '\r') i++;
          return i;
        }
        return -1; // trailing non-whitespace code on the line -> refuse to strip
      }
    }
  }
  return -1; // unbalanced
}

/**
 * Detects malformed narration that survived stripping.
 *
 * Callers refuse the bundle when this returns true because hidden narration statements could change
 * the verdict run or make the displayed spec differ from the executed one.
 */
export const hasLeftoverNarration = (stripped: string): boolean =>
  /\bfrom\s+['"]\.\/video-helpers\.js['"]/.test(stripped) || /\bawait\s+(?:narrate|mark)\(/.test(stripped);

if (import.meta.url === `file://${process.argv[1]}`) {
  const [, , input, output] = process.argv;
  if (!input) {
    console.error('usage: strip-narration.ts <input> [output]');
    process.exit(2);
  }
  const out = stripNarration(fs.readFileSync(input, 'utf8'));
  if (output) {
    fs.writeFileSync(output, out);
  } else {
    process.stdout.write(out);
  }
}
