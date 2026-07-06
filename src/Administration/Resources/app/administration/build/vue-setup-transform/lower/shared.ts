/**
 * @sw-package framework
 */

import { transformRanges } from '../source-edits/transform-ranges';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { SourceChunk } from '../source-edits/chunks';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';

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
 * Applies analyzer-provided source ranges to produce the callback body.
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

export {
    type SetupInputNames,
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    formatObjectProperties,
};
