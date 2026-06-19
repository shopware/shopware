/**
 * @sw-package framework
 */

const { transformRanges } = require('../source-edits/transform-ranges');

/**
 * @typedef {import('../utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('../script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 * @typedef {import('../source-edits/chunks').SourceChunk} SourceChunk
 */

/**
 * Escapes component names embedded in generated single-quoted strings.
 *
 * @param {string} value
 * @returns {string}
 */
function escapeSingleQuoted(value) {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/**
 * Formats deterministic object literals for exact-string transform tests.
 *
 * @param {string[]} properties
 * @param {number} [spaces]
 * @returns {string}
 */
function formatObjectProperties(properties, spaces = 12) {
    if (properties.length === 0) {
        return '{}';
    }

    const indentation = ' '.repeat(spaces);
    const closingIndentation = ' '.repeat(spaces - 4);

    return `{\n${properties.map((property) => `${indentation}${property},`).join('\n')}\n${closingIndentation}}`;
}

/**
 * Avoids generated helper names colliding with user imports or declarations.
 *
 * @param {string} baseName
 * @param {Set<string>} takenNames
 * @returns {string}
 */
function makeUniqueName(baseName, takenNames) {
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
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @param {{ props: string, context: string } | null} setupInputNames
 * @returns {SourceChunk[]}
 */
function buildCallbackBodyChunks(block, analysis, setupInputNames) {
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
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {Set<string>}
 */
function getTakenNames(analysis) {
    return new Set([
        ...analysis.runtimeBindings.map((binding) => binding.name),
        ...Array.from(analysis.importedBindings),
    ]);
}

module.exports = {
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    formatObjectProperties,
    getTakenNames,
    makeUniqueName,
};
