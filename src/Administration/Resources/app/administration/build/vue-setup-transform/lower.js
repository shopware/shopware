/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('./script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 * @typedef {import('./script-analyzer').PublicEntry} PublicEntry
 * @typedef {import('./script-analyzer').ImportBlock} ImportBlock
 * @typedef {PublicEntry['key']} PublicKey
 */

const RUNTIME_IMPORT = 'src/app/adapter/composition-extension-system';

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
 * Escapes component names and public keys embedded in generated single-quoted strings.
 *
 * @param {string} value
 * @returns {string}
 */
function escapeSingleQuoted(value) {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/**
 * Formats public API keys, preserving identifier shorthand where it is readable.
 *
 * @param {PublicKey} key
 * @returns {string}
 */
function formatPropertyKey(key) {
    if (key.type === 'identifier') {
        return key.value;
    }

    return `'${escapeSingleQuoted(key.value)}'`;
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
 * Indexes public entries by local binding and rejects duplicate local exposure.
 *
 * @param {PublicEntry[]} publicEntries
 * @returns {Map<string, PublicEntry>}
 */
function createPublicEntryByLocalNameMap(publicEntries) {
    const publicEntryByLocalName = new Map();

    publicEntries.forEach((entry) => {
        if (publicEntryByLocalName.has(entry.localName)) {
            throw new ShopwareSetupTransformError(
                `Local binding "${entry.localName}" cannot be listed multiple times in swDefinePublic().`,
                0,
            );
        }

        publicEntryByLocalName.set(entry.localName, entry);
    });

    return publicEntryByLocalName;
}

/**
 * Emits the destructured bridge result entry for public aliases such as `{ 'foo': foo2 }`.
 *
 * @param {string} bindingName
 * @param {PublicEntry | undefined} publicEntry
 * @returns {string}
 */
function formatDestructureEntry(bindingName, publicEntry) {
    if (!publicEntry) {
        return bindingName;
    }

    if (publicEntry.key.type === 'identifier' && publicEntry.key.value === bindingName) {
        return bindingName;
    }

    return `${formatPropertyKey(publicEntry.key)}: ${bindingName}`;
}

/**
 * Builds the base callback return object split into public and private state.
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function buildBaseReturn(analysis) {
    const publicLocalNames = new Set(analysis.publicEntries.map((entry) => entry.localName));
    const privateProperties = analysis.runtimeBindings
        .filter((binding) => !publicLocalNames.has(binding.name))
        .map((binding) => binding.name);
    const publicProperties = analysis.publicEntries.map((entry) => {
        if (entry.key.type === 'identifier' && entry.key.value === entry.localName) {
            return entry.localName;
        }

        return `${formatPropertyKey(entry.key)}: ${entry.localName}`;
    });

    if (analysis.runtimeBindings.length === 0) {
        throw new ShopwareSetupTransformError(
            'A base Shopware setup block must declare at least one top-level runtime binding.',
            0,
        );
    }

    return [
        'return {',
        `    public: ${formatObjectProperties(publicProperties, 8)},`,
        `    private: ${formatObjectProperties(privateProperties, 8)},`,
        '};',
    ].join('\n');
}

/**
 * Builds the override callback payload from top-level runtime bindings.
 *
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function buildOverrideReturn(analysis) {
    if (analysis.runtimeBindings.length === 0) {
        return 'return {};';
    }

    return [
        'return {',
        ...analysis.runtimeBindings.map((binding) => `    ${binding.name},`),
        '};',
    ].join('\n');
}

/**
 * Keeps user imports before generated runtime imports for readable transformed output.
 *
 * @param {ImportBlock[]} userImports
 * @param {string} helperImport
 * @returns {string}
 */
function buildImports(userImports, helperImport) {
    if (userImports.length === 0) {
        return helperImport;
    }

    return `${userImports.map((importBlock) => importBlock.code).join('\n')}\n\n${helperImport}`;
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
    const createHelperName = makeUniqueName('__swCreateScriptSetupExtendableComponent', takenNames);
    const setupBindingsName = makeUniqueName('__shopwareSetupBindings', takenNames);
    const publicEntryByLocalName = createPublicEntryByLocalNameMap(analysis.publicEntries);
    const destructureEntries = analysis.runtimeBindings.map((binding) => {
        return formatDestructureEntry(binding.name, publicEntryByLocalName.get(binding.name));
    });
    const helperImport = `import { createScriptSetupExtendableComponent as ${createHelperName} } from '${RUNTIME_IMPORT}';`;
    const body = [
        `const useSwProps = () => ${setupBindingsName}.props;`,
        `const useSwContext = () => ${setupBindingsName}.context;`,
        '',
        analysis.body,
        '',
        buildBaseReturn(analysis),
    ].join('\n');

    return [
        `<script${block.passthroughAttributesSource}>`,
        buildImports(analysis.imports, helperImport),
        '',
        'const {',
        ...destructureEntries.map((entry) => `    ${entry},`),
        `} = ${createHelperName}()('${escapeSingleQuoted(block.componentName)}', (${setupBindingsName}) => {`,
        indent(body),
        '});',
        '</script>',
    ].join('\n');
}

/**
 * Lowers override mode into import-time override registration.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {string}
 */
function buildOverrideScript(block, analysis) {
    const takenNames = getTakenNames(analysis);
    const overrideHelperName = makeUniqueName('__swOverrideComponentSetup', takenNames);
    const previousStateName = makeUniqueName('__swPreviousState', takenNames);
    const propsName = makeUniqueName('__swProps', takenNames);
    const contextName = makeUniqueName('__swContext', takenNames);
    const helperImport = `import { overrideComponentSetup as ${overrideHelperName} } from '${RUNTIME_IMPORT}';`;
    const passthroughAttributesSource = block.attributes.toSourceWithout([
        'setup',
        'sw-component',
        'sw-override',
    ]);
    const body = [
        `const useSwPreviousState = () => ${previousStateName};`,
        `const useSwProps = () => ${propsName};`,
        `const useSwContext = () => ${contextName};`,
        '',
        analysis.body,
        '',
        buildOverrideReturn(analysis),
    ].join('\n');

    return [
        `<script${passthroughAttributesSource}>`,
        buildImports(analysis.imports, helperImport),
        '',
        `${overrideHelperName}()('${escapeSingleQuoted(block.componentName)}', (${previousStateName}, ${propsName}, ${contextName}) => {`,
        indent(body),
        '});',
        '',
        'export default {};',
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
