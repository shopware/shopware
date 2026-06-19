/**
 * @sw-package framework
 */

const {
    fromSource,
    generated,
    indent,
} = require('../source-edits/chunks');
const {
    buildCallbackBodyChunks,
    escapeSingleQuoted,
    getTakenNames,
    makeUniqueName,
} = require('./shared');

/**
 * @typedef {import('../utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('../script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 */

/**
 * Builds the override callback payload from declared replacements and template-used private aliases.
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function buildOverrideReturn(analysis) {
    const privateBindings = Array.from(analysis.overridePrivateBindings);

    if (analysis.overrideEntries.length === 0 && privateBindings.length === 0) {
        return 'return {};';
    }

    const lines = [
        'return {',
        ...analysis.overrideEntries.map((property) => `    ${property},`),
    ];

    if (privateBindings.length > 0) {
        lines.push(
            '    __swOverride: {',
            `        ${analysis.overridePrivateNamespace}: {`,
            ...privateBindings.map((localName) => `            ${localName},`),
            '        },',
            '    },',
        );
    }

    lines.push('};');

    return lines.join('\n');
}

/**
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {import('../source-edits/chunks').SourceChunk[]}
 */
function buildOverrideScript(block, analysis) {
    const takenNames = getTakenNames(analysis);
    const previousStateName = makeUniqueName('__swPreviousState', takenNames);
    const propsName = makeUniqueName('__swProps', takenNames);
    const contextName = makeUniqueName('__swContext', takenNames);
    const passthroughAttributesSource = block.attributes.toSourceWithout([
        'setup',
        'sw-component',
        'sw-override',
    ]);
    const callbackBody = buildCallbackBodyChunks(block, analysis, null);
    const body = [
        generated(`const useSwPreviousState = () => ${previousStateName};\n`),
        generated(`const useSwProps = () => ${propsName};\n`),
        generated(`const useSwContext = () => ${contextName};\n\n`),
        ...callbackBody,
        generated(`\n\n${buildOverrideReturn(analysis)}`),
    ];
    const chunks = [
        generated(`<script${passthroughAttributesSource}>\n`),
    ];

    analysis.imports.forEach((importBlock) => {
        chunks.push(fromSource(block, importBlock.start, importBlock.end));
        chunks.push(generated('\n'));
    });

    if (analysis.imports.length > 0) {
        chunks.push(generated('\n'));
    }

    chunks.push(
        generated([
            'export default {',
            '    setup() {',
            `        Shopware.Component.overrideComponentSetup()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        ].join('\n')),
        generated('\n'),
        indent(body, 12),
        generated('\n        });'),
    );

    if (!block.template) {
        chunks.push(generated('\n\n        return () => null;'));
    }

    chunks.push(generated('\n    },\n};\n</script>'));

    return chunks;
}

module.exports = {
    buildOverrideScript,
};
