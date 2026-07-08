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
 * Names the generated callback inputs that replace Vue setup macros inside a base setup body.
 */
type SetupInputNames = {
    props: string;
    context: string;
};

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
 * Applies analyzer-provided source ranges to produce the generated setup callback body.
 *
 * In base mode the Vue setup macros are replaced with callback parameters. In override mode no
 * replacements are passed because override helpers such as `useSwProps()` are generated instead.
 */
function buildCallbackBodyChunks(
    block: ShopwareSetupBlock,
    analysis: ShopwareSetupScriptAnalysis,
    setupInputNames: SetupInputNames | null,
): SourceChunk[] {
    return transformRanges(
        block,
        analysis,
        analysis.bodyRemovals,
        setupInputNames
            ? analysis.setupInputReplacements.map((range) => ({
                  ...range,
                  replacement: {
                      props: `(${setupInputNames.props})`,
                      emits: `(${setupInputNames.context}.emit)`,
                      expose: `(${setupInputNames.context}.expose)`,
                      slots: `(${setupInputNames.context}.slots)`,
                  }[range.kind],
              }))
            : [],
    );
}

export { type SetupInputNames, buildCallbackBodyChunks, escapeSingleQuoted, formatObjectProperties };
