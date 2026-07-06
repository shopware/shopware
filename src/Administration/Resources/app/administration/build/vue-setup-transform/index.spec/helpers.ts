/**
 * @sw-package framework
 */

import { transformShopwareSetupSfc } from '../index';
import { parse, compileScript } from '@vue/compiler-sfc';

type TransformResult = NonNullable<ReturnType<typeof transformShopwareSetupSfc>>;

/**
 * Keeps positive transform assertions typed and avoids repeated non-null assumptions.
 */
function transformOrFail(source: string, filename: string): TransformResult {
    const result = transformShopwareSetupSfc(source, filename);

    expect(result).not.toBeNull();

    return result as TransformResult;
}

function stripIndent(strings: TemplateStringsArray, ...values: string[]): string {
    const value = strings.reduce((result: string, string: string, index: number) => {
        return `${result}${string}${values[index] ?? ''}`;
    }, '');
    const lines = value.replace(/\r\n/g, '\n').split('\n');

    if (lines[0]?.trim() === '') {
        lines.shift();
    }

    if (lines[lines.length - 1]?.trim() === '') {
        lines.pop();
    }

    const indentation = lines
        .filter((line: string) => line.trim() !== '')
        .map((line: string) => line.match(/^\s*/)?.[0].length ?? 0);
    const minIndentation = Math.min(...indentation);

    return lines.map((line: string) => line.slice(minIndentation)).join('\n');
}

function expectVueCompilerScriptToCompile(code: string, filename: string): void {
    const descriptor = parse(code, { filename }).descriptor;

    expect(() => compileScript(descriptor, { id: filename })).not.toThrow();
}

export { expectVueCompilerScriptToCompile, stripIndent, transformOrFail, transformShopwareSetupSfc };
