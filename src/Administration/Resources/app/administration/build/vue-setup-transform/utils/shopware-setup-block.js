/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('./transform-error');

/**
 * @typedef {import('./sfc-script-block').ScriptBlock} ScriptBlock
 *
 * @typedef {ScriptBlock & {
 *     mode: 'base' | 'override',
 *     componentName: string,
 *     lang: string | null,
 * }} ShopwareSetupBlock
 */

const SUPPORTED_LANGUAGES = new Set([
    'js',
    'jsx',
    'ts',
    'tsx',
]);

/**
 * Validates and returns `sw-component` / `sw-override` string literals.
 *
 * @param {ScriptBlock} block
 * @param {'sw-component' | 'sw-override'} attributeName
 * @returns {string | null}
 */
function assertStaticModeAttribute(block, attributeName) {
    const attribute = block.attributes.get(attributeName);

    if (!attribute) {
        return null;
    }

    if (!attribute.hasValue || attribute.value === true || !attribute.quoted) {
        throw new ShopwareSetupTransformError(
            `The ${attributeName} attribute must use a static quoted string value.`,
            attribute.index,
        );
    }

    if (attribute.value.length === 0) {
        throw new ShopwareSetupTransformError(`The ${attributeName} attribute must not be empty.`, attribute.index);
    }

    return attribute.value;
}

/**
 * Normalizes missing `lang` to `null` while rejecting languages the analyzer cannot parse.
 *
 * @param {ScriptBlock} block
 * @returns {string | null}
 */
function resolveScriptLanguage(block) {
    const langAttribute = block.attributes.get('lang');

    if (!langAttribute) {
        return null;
    }

    if (!langAttribute.hasValue || langAttribute.value === true || !langAttribute.quoted) {
        throw new ShopwareSetupTransformError(
            'The lang attribute on a Shopware setup block must be a static quoted value.',
            langAttribute.index,
        );
    }

    if (!SUPPORTED_LANGUAGES.has(langAttribute.value)) {
        throw new ShopwareSetupTransformError(
            `Unsupported Shopware setup script language "${langAttribute.value}". Supported languages are js, jsx, ts, and tsx.`,
            langAttribute.index,
        );
    }

    return langAttribute.value;
}

/**
 * Turns a generic script setup block into the explicit base/override Shopware mode.
 *
 * @param {ScriptBlock} block
 * @returns {ShopwareSetupBlock | null}
 */
function normalizeShopwareSetupBlock(block) {
    if (block.attributes.hasBoundAttributes()) {
        throw new ShopwareSetupTransformError(
            'Shopware setup mode attributes must be static strings, not bound expressions.',
            block.start,
        );
    }

    if (!block.attributes.hasShopwareSetupModeAttribute()) {
        return null;
    }

    const componentName = assertStaticModeAttribute(block, 'sw-component');
    const overrideName = assertStaticModeAttribute(block, 'sw-override');

    if (componentName && overrideName) {
        throw new ShopwareSetupTransformError(
            'Use either sw-component or sw-override on a Shopware setup block, not both.',
            block.start,
        );
    }

    if (!componentName && !overrideName) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block requires either sw-component or sw-override.',
            block.start,
        );
    }

    return {
        ...block,
        mode: componentName ? 'base' : 'override',
        componentName: componentName ?? overrideName,
        lang: resolveScriptLanguage(block),
    };
}

module.exports = {
    SUPPORTED_LANGUAGES,
    normalizeShopwareSetupBlock,
};
