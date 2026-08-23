/**
 * @sw-package framework
 */

// Import the TypeScript entry explicitly: a bare '../index' resolves to the CJS jiti bridge
// (index.js) under Jest, which loads the real module outside Jest's transform pipeline and leaves
// every transform source reported as 0% covered. The '.ts' specifier keeps coverage attribution
// on the actual source.
import { transformShopwareSetupSfc } from '../index.ts';
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

/**
 * Collapses every whitespace run to a single space, so an exact-output assertion can be written with
 * whatever indentation reads best instead of mirroring the generated layout.
 *
 * Applied to BOTH sides of the comparison. The transform does not beautify its output (no re-indenting,
 * no trimming), so its blank-line and indentation residue is not behaviour worth pinning - but the
 * token sequence is. Pair it with `expectVueCompilerScriptToCompile` on the RAW output: whitespace
 * collapse cannot see a swallowed newline before a `//` comment, and Vue's parser can.
 *
 * Usable as a template tag (for the expected literal) or as a function (for the actual code).
 */
function stripWhitespace(value: string | TemplateStringsArray, ...values: string[]): string {
    const joined =
        typeof value === 'string'
            ? value
            : value.reduce((result, part, index) => `${result}${part}${values[index] ?? ''}`, '');

    return joined.replace(/\s+/g, ' ').trim();
}

function expectVueCompilerScriptToCompile(code: string, filename: string): void {
    const descriptor = parse(code, { filename }).descriptor;

    expect(() => compileScript(descriptor, { id: filename })).not.toThrow();
}

/**
 * Asserts Vue's own compiler rejects the transformed output, with the message it owns.
 *
 * The counterpart to `expectVueCompilerScriptToCompile`: used where a constraint belongs to Vue rather
 * than to this transform, so the spec pins that we pass the code through and Vue reports it - instead of
 * duplicating the check with a message of our own.
 */
function expectVueCompilerScriptToReject(code: string, filename: string, message: string): void {
    const descriptor = parse(code, { filename }).descriptor;

    expect(() => compileScript(descriptor, { id: filename })).toThrow(message);
}

/**
 * @private
 */
export {
    expectVueCompilerScriptToCompile,
    expectVueCompilerScriptToReject,
    stripIndent,
    stripWhitespace,
    transformOrFail,
    transformShopwareSetupSfc,
};
