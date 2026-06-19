/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('../utils/transform-error');
const {
    fromSource,
    generated,
    indent,
} = require('../source-edits/chunks');
const {
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    formatObjectProperties,
    getTakenNames,
    makeUniqueName,
} = require('./shared');

/**
 * @typedef {import('../utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('../script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 */

/**
 * Builds the base callback return object split into public and private state.
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function buildBaseReturn(analysis) {
    const publicLocalNames = new Set(analysis.publicEntries);
    const privateProperties = analysis.runtimeBindings
        .filter((binding) => !publicLocalNames.has(binding.name))
        .map((binding) => binding.name);

    if (analysis.runtimeBindings.length === 0) {
        throw new ShopwareSetupTransformError(
            'A base Shopware setup block must declare at least one top-level runtime binding.',
            0,
        );
    }

    return [
        'return {',
        `    public: ${formatObjectProperties(analysis.publicEntries, 8)},`,
        `    private: ${formatObjectProperties(privateProperties, 8)},`,
        '};',
    ].join('\n');
}

/**
 * Lowers base mode into the existing extendable setup runtime bridge.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {import('../source-edits/chunks').SourceChunk[]}
 */
function buildBaseScript(block, analysis) {
    const takenNames = getTakenNames(analysis);
    const setupBindingsName = makeUniqueName('__shopwareSetupBindings', takenNames);
    const propsName = analysis.propsMacro ? makeUniqueName('props', takenNames) : null;
    const emitName = analysis.emitsMacro ? makeUniqueName('emit', takenNames) : null;
    const slotsName = analysis.slotsMacro ? makeUniqueName('slots', takenNames) : null;
    const destructureEntries = [
        ...analysis.runtimeBindings.map((binding) => binding.name),
        '__swOverride',
    ];
    const callbackBody = buildCallbackBodyChunks(block, analysis, setupBindingsName);
    const body = [
        generated(`const useSwContext = () => ${setupBindingsName}.context;\n\n`),
        ...callbackBody,
        generated(`\n\n${buildBaseReturn(analysis)}`),
    ];
    const chunks = [
        generated(`<script${block.passthroughAttributesSource}>\n`),
    ];

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock.start, importBlock.end));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    if (analysis.propsMacro) {
        chunks.push(generated(`const ${propsName} = `));
        chunks.push(fromSource(block, analysis.propsMacro.ranges[0].start, analysis.propsMacro.ranges[0].end));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.emitsMacro) {
        chunks.push(generated(`const ${emitName} = `));
        chunks.push(fromSource(block, analysis.emitsMacro.ranges[0].start, analysis.emitsMacro.ranges[0].end));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.slotsMacro) {
        chunks.push(generated(`const ${slotsName} = `));
        chunks.push(fromSource(block, analysis.slotsMacro.ranges[0].start, analysis.slotsMacro.ranges[0].end));
        chunks.push(generated(';\n\n'));
    }

    if (analysis.optionsMacro) {
        chunks.push(fromSource(block, analysis.optionsMacro.ranges[0].start, analysis.optionsMacro.ranges[0].end));
        chunks.push(generated(';\n\n'));
    }

    chunks.push(
        generated([
            'const {',
            ...destructureEntries.map((entry) => `    ${entry},`),
            `} = Shopware.Component.createScriptSetupExtendableComponent()('${escapeSingleQuoted(block.componentName)}', ${propsName ? `${propsName}, ` : ''}(${setupBindingsName}) => {`,
        ].join('\n')),
        generated('\n'),
        indent(body),
        generated('\n});\n</script>'),
    );

    return chunks;
}

module.exports = {
    buildBaseScript,
};
