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
 * Reads the override-private namespace key back out of a transform result.
 *
 * An override's non-public setup bindings are forwarded to the template through the shared
 * `__swOverride` data-scope object under a **computed** key - the module's namespace Symbol, for example
 * `__swOverride: { [__swSetupNamespace]: { count } }`. Returns the key text including its brackets, so
 * callers can compose it straight into an expected string.
 */
function getPrivateNamespace(result: string): string | undefined {
    return (
        result.match(/__swOverride: \{\n\s+(\[[A-Za-z_$][A-Za-z0-9_$]*\]): \{/)?.[1] ??
        result.match(/__swOverride: \{ (\[[A-Za-z_$][A-Za-z0-9_$]*\]): \{/)?.[1]
    );
}

/**
 * @private
 */
export {
    expectVueCompilerScriptToCompile,
    expectVueCompilerScriptToReject,
    getPrivateNamespace,
    stripIndent,
    transformOrFail,
    transformShopwareSetupSfc,
};
