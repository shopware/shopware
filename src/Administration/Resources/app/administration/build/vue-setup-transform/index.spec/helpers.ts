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

/**
 * Reads the override-private namespace key back out of a transform result.
 *
 * An override's non-public setup bindings are forwarded to the template through the shared
 * `__swOverride` data-scope object under a per-file namespace key, for example
 * `__swOverride: { my_component_override_1a2b3: { count } }`. The key is `<file>_<5-hex-sha1>`,
 * deterministic and unique per override file so several overrides on the same base component never
 * collide. Tests match the key by shape instead of hardcoding the hash.
 */
function getPrivateNamespace(result: string): string | undefined {
    return (
        result.match(/__swOverride: \{\n\s+([A-Za-z_$][A-Za-z0-9_$]*_[a-f0-9]{5}): \{/)?.[1] ??
        result.match(/__swOverride: \{ ([A-Za-z_$][A-Za-z0-9_$]*_[a-f0-9]{5}): \{/)?.[1]
    );
}

export {
    expectVueCompilerScriptToCompile,
    getPrivateNamespace,
    stripIndent,
    transformOrFail,
    transformShopwareSetupSfc,
};
