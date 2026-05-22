/**
 * @sw-package framework
 * @private
 */

/** @private */
export function quoteJsString(value: string): string {
    const body = value
        .replaceAll('\\', '\\\\')
        .replaceAll('\'', '\\\'');
    return `'${body}'`;
}
