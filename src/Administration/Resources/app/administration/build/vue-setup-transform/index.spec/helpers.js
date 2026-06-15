/**
 * @sw-package framework
 */

import { transformShopwareSetupSfc } from '../index';

/**
 * @typedef {NonNullable<ReturnType<typeof transformShopwareSetupSfc>>} TransformResult
 */

/**
 * Keeps positive transform assertions typed and avoids repeated non-null assumptions.
 *
 * @param {string} source
 * @param {string} filename
 * @returns {TransformResult}
 */
function transformOrFail(source, filename) {
    const result = transformShopwareSetupSfc(source, filename);

    expect(result).not.toBeNull();

    return result;
}

function stripIndent(strings, ...values) {
    const value = strings.reduce((result, string, index) => {
        return `${result}${string}${values[index] ?? ''}`;
    }, '');
    const lines = value.replace(/\r\n/g, '\n').split('\n');

    if (lines[0]?.trim() === '') {
        lines.shift();
    }

    if (lines[lines.length - 1]?.trim() === '') {
        lines.pop();
    }

    const indentation = lines.filter((line) => line.trim() !== '').map((line) => line.match(/^\s*/)?.[0].length ?? 0);
    const minIndentation = Math.min(...indentation);

    return lines.map((line) => line.slice(minIndentation)).join('\n');
}

export { stripIndent, transformOrFail, transformShopwareSetupSfc };
