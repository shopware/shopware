/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('./utils/transform-error');
const {
    fromSource,
    generated,
    indent,
} = require('./source-edits/chunks');
const { render } = require('./source-edits/render-chunks');
const { transformRanges } = require('./source-edits/transform-ranges');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('./script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 */

/**
 * @typedef {import('./source-edits/chunks').SourceChunk} SourceChunk
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
 * @param {string | null} setupBindingsName
 * @returns {SourceChunk[]}
 */
function buildCallbackBodyChunks(block, analysis, setupBindingsName) {
    return transformRanges(
        block,
        analysis,
        analysis.bodyRemovals,
        setupBindingsName
            ? analysis.setupInputReplacements.map((range) => ({
                  ...range,
                  replacement: {
                      props: `(${setupBindingsName}.props)`,
                      emits: `(${setupBindingsName}.context.emit)`,
                      expose: `(${setupBindingsName}.context.expose)`,
                      slots: `(${setupBindingsName}.context.slots)`,
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
 * Lowers base mode into the existing extendable setup runtime bridge.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
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

/**
 * Lowers override mode into a hidden override component consumed by
 * registerOverrideComponent.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
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

/**
 * Dispatches to the mode-specific lowering path after shared analysis has completed.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function lowerShopwareSetupBlock(block, analysis) {
    const chunks = block.mode === 'base' ? buildBaseScript(block, analysis) : buildOverrideScript(block, analysis);

    return {
        chunks,
        code: render(chunks, analysis.source, block.contentStart),
    };
}

module.exports = {
    lowerShopwareSetupBlock,
};
