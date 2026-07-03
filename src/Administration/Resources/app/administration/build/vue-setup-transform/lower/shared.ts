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
 * Avoids generated helper names colliding with user imports or declarations.
 */
function makeUniqueName(baseName: string, takenNames: Set<string>): string {
    let name = baseName;
    let counter = 2;

    while (takenNames.has(name)) {
        name = `${baseName}${counter}`;
        counter += 1;
    }

    takenNames.add(name);

    return name;
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

/**
 * Collects names that generated helpers must not reuse.
 */
function getTakenNames(analysis: ShopwareSetupScriptAnalysis): Set<string> {
    return new Set([
        ...analysis.runtimeBindings.map((binding) => binding.name),
        ...Array.from(analysis.importedBindings),
    ]);
}

export {
    type SetupInputNames,
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    formatObjectProperties,
    getTakenNames,
    makeUniqueName,
};
