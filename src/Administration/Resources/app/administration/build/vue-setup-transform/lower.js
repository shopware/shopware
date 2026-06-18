/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('./script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 */

/**
 * Indents generated callback bodies while preserving intentionally blank spacer lines.
 *
 * @param {string} code
 * @param {number} [spaces]
 * @returns {string}
 */
function indent(code, spaces = 4) {
    const indentation = ' '.repeat(spaces);

    return code
        .split('\n')
        .map((line) => (line.length > 0 ? `${indentation}${line}` : line))
        .join('\n');
}

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
 * Removes macro/import ranges and replaces selected expressions in the code that moves into the runtime callback.
 *
 * @param {string} source
 * @param {{ start: number, end: number }[]} removals
 * @param {({ start: number, end: number } & { replacement: string })[]} [replacements]
 * @returns {string}
 */
function transformRanges(source, removals, replacements = []) {
    const sortedRanges = [
        ...removals.map((range) => ({
            ...range,
            replacement: '',
        })),
        ...replacements,
    ].sort((a, b) => {
        if (a.start === b.start) {
            return b.end - a.end;
        }

        return a.start - b.start;
    });
    let cursor = 0;
    let output = '';

    sortedRanges.forEach((range) => {
        if (range.start < cursor) {
            return;
        }

        output += source.slice(cursor, range.start);
        output += range.replacement;
        cursor = range.end;
    });

    output += source.slice(cursor);

    return output.trim();
}

/**
 * Applies analyzer-provided source ranges to produce the callback body.
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @param {string | null} setupBindingsName
 * @returns {string}
 */
function buildCallbackBody(analysis, setupBindingsName) {
    return transformRanges(
        analysis.source,
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
    const privateProperties = Array.from(analysis.overridePrivateAliases.entries()).map(
        ([
            localName,
            privateAlias,
        ]) => {
            return `${privateAlias}: ${localName}`;
        },
    );
    const properties = [
        ...analysis.overrideEntries,
        ...privateProperties,
    ];

    if (properties.length === 0) {
        return 'return {};';
    }

    return [
        'return {',
        ...properties.map((property) => `    ${property},`),
        '};',
    ].join('\n');
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
    const destructureEntries = analysis.runtimeBindings.map((binding) => binding.name);
    const callbackBody = buildCallbackBody(analysis, setupBindingsName);
    const body = [
        `const useSwContext = () => ${setupBindingsName}.context;`,
        '',
        callbackBody,
        '',
        buildBaseReturn(analysis),
    ].join('\n');

    return [
        `<script${block.passthroughAttributesSource}>`,
        ...analysis.imports.map((importBlock) => importBlock.code),
        ...(analysis.imports.length > 0 ? [''] : []),
        ...(analysis.propsMacro
            ? [
                  `const ${propsName} = ${analysis.propsMacro.code};`,
                  '',
              ]
            : []),
        ...(analysis.emitsMacro
            ? [
                  `const ${emitName} = ${analysis.emitsMacro.code};`,
                  '',
              ]
            : []),
        ...(analysis.slotsMacro
            ? [
                  `const ${slotsName} = ${analysis.slotsMacro.code};`,
                  '',
              ]
            : []),
        ...(analysis.optionsMacro
            ? [
                  analysis.optionsMacro.code,
                  '',
              ]
            : []),
        'const {',
        ...destructureEntries.map((entry) => `    ${entry},`),
        `} = Shopware.Component.createScriptSetupExtendableComponent()('${escapeSingleQuoted(block.componentName)}', ${propsName ? `${propsName}, ` : ''}(${setupBindingsName}) => {`,
        indent(body),
        '});',
        '</script>',
    ].join('\n');
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
    const callbackBody = buildCallbackBody(analysis, null);
    const body = [
        `const useSwPreviousState = () => ${previousStateName};`,
        `const useSwProps = () => ${propsName};`,
        `const useSwContext = () => ${contextName};`,
        '',
        callbackBody,
        '',
        buildOverrideReturn(analysis),
    ].join('\n');

    return [
        `<script${passthroughAttributesSource}>`,
        ...analysis.imports.map((importBlock) => importBlock.code),
        ...(analysis.imports.length > 0 ? [''] : []),
        'export default {',
        '    setup() {',
        `        Shopware.Component.overrideComponentSetup()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        indent(body, 12),
        '        });',
        ...(block.template
            ? []
            : [
                  '',
                  '        return () => null;',
              ]),
        '    },',
        '};',
        '</script>',
    ].join('\n');
}

/**
 * Dispatches to the mode-specific lowering path after shared analysis has completed.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function lowerShopwareSetupBlock(block, analysis) {
    if (block.mode === 'base') {
        return buildBaseScript(block, analysis);
    }

    return buildOverrideScript(block, analysis);
}

module.exports = {
    lowerShopwareSetupBlock,
};
