/**
 * @sw-package framework
 * @private
 */

/** @private */
export function quoteJsString(value: string): string {
    return `'${JSON.stringify(value).slice(1, -1).replace(/'/g, "\\'")}'`;
}
