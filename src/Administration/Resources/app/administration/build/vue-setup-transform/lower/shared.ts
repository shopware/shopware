/**
 * @sw-package framework
 */

/**
 * Provides shared code-generation helpers for base and override lowerers.
 *
 * These helpers translate analyzer-owned source ranges into callback body chunks and map Vue setup
 * macros to the generated Shopware callback parameters.
 */

import { transformRanges } from '../source-edits/transform-ranges';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { SourceChunk } from '../source-edits/chunks';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';

/**
 * Escapes component names embedded in generated single-quoted strings.
 */
function escapeSingleQuoted(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/**
 * Formats deterministic object literals for exact-string transform tests.
 */
function formatObjectProperties(properties: string[], spaces = 12): string {
    if (properties.length === 0) {
        return '{}';
    }

    const indentation = ' '.repeat(spaces);
    const closingIndentation = ' '.repeat(spaces - 4);

    return `{\n${properties.map((property) => `${indentation}${property},`).join('\n')}\n${closingIndentation}}`;
}

/**
 * Applies analyzer-provided source ranges to produce the generated override callback body.
 *
 * Only the marker statements (`swDefineOverride(...)`) are removed; override helpers such as
 * `useSwProps()` are emitted as generated header lines by the override lowerer, so no in-body macro
 * replacement is needed.
 */
function buildCallbackBodyChunks(block: ShopwareSetupBlock, analysis: ShopwareSetupScriptAnalysis): SourceChunk[] {
    return transformRanges(block, analysis.bodyRemovals, []);
}

export { buildCallbackBodyChunks, escapeSingleQuoted, formatObjectProperties };
