/**
 * @sw-package framework
 * @private
 */

/**
 * Quotes a value as a single-quoted JavaScript string literal.
 *
 * Control characters and the Unicode line separators are escaped as well: a raw
 * one between the quotes ends the literal or silently changes its value, so an
 * inject or provide key containing a line break would emit a file that no longer
 * parses.
 *
 * @private
 */
export function quoteJsString(value: string): string {
    const body = value
        .replaceAll('\\', '\\\\')
        .replaceAll("'", "\\'")
        .replace(/[\p{Cc}\p{Zl}\p{Zp}]/gu, (char) => `\\u${char.charCodeAt(0).toString(16).padStart(4, '0')}`);
    return `'${body}'`;
}
