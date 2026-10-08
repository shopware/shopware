/**
 * @sw-package framework
 */

/**
 * Provides shared code-generation helpers for base and override lowerers.
 *
 * Small string helpers for the two lowerers. Range-to-chunk translation lives in
 * `source-edits/transform-ranges`, which the override lowerer calls directly.
 */

/**
 * Escapes component names embedded in generated single-quoted strings.
 */
function escapeSingleQuoted(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/**
 * Formats deterministic object literals for exact-string transform tests.
 */
function formatObjectProperties(properties: string[], spaces: number): string {
    if (properties.length === 0) {
        return '{}';
    }

    const indentation = ' '.repeat(spaces);
    const closingIndentation = ' '.repeat(spaces - 4);

    return `{\n${properties.map((property) => `${indentation}${property},`).join('\n')}\n${closingIndentation}}`;
}

/**
 * @private
 */
export { escapeSingleQuoted, formatObjectProperties };
