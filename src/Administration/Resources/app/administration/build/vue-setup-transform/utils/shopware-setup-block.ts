/**
 * @sw-package framework
 */

import type { ScriptBlock } from './sfc-script-block';
import { ShopwareSetupTransformError } from './transform-error';

type ShopwareSetupMode = 'base' | 'override';

type ShopwareSetupTemplate = {
    content: string,
    contentStart: number,
};

type ShopwareSetupBlock = ScriptBlock & {
    mode: ShopwareSetupMode,
    componentName: string,
    lang: string | null,
    template: ShopwareSetupTemplate | null,
    filename: string,
};

const SUPPORTED_LANGUAGES = new Set([
    'js',
    'jsx',
    'ts',
    'tsx',
]);

/**
 * Validates and returns `sw-component` / `sw-override` string literals.
 */
function assertStaticModeAttribute(block: ScriptBlock, attributeName: 'sw-component' | 'sw-override'): string | null {
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
 */
function resolveScriptLanguage(block: ScriptBlock): string | null {
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
 */
function normalizeShopwareSetupBlock(block: ScriptBlock): Omit<ShopwareSetupBlock, 'template' | 'filename'> | null {
    if (block.attributes.hasBoundAttributes()) {
        throw new ShopwareSetupTransformError(
            'Shopware setup mode attributes must be static strings, not bound expressions.',
            block.start,
        );
    }

    if (!block.attributes.hasShopwareSetupModeAttribute()) {
        return null;
    }

    const componentNameAttribute = assertStaticModeAttribute(block, 'sw-component');
    const overrideName = assertStaticModeAttribute(block, 'sw-override');

    if (componentNameAttribute && overrideName) {
        throw new ShopwareSetupTransformError(
            'Use either sw-component or sw-override on a Shopware setup block, not both.',
            block.start,
        );
    }

    if (!componentNameAttribute && !overrideName) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block requires either sw-component or sw-override.',
            block.start,
        );
    }

    const componentName = componentNameAttribute ?? overrideName;

    if (!componentName) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block requires either sw-component or sw-override.',
            block.start,
        );
    }

    return {
        ...block,
        mode: componentNameAttribute ? 'base' : 'override',
        componentName,
        lang: resolveScriptLanguage(block),
    };
}

module.exports = {
    SUPPORTED_LANGUAGES,
    normalizeShopwareSetupBlock,
};

export {
    type ShopwareSetupBlock,
    type ShopwareSetupMode,
    type ShopwareSetupTemplate,
    SUPPORTED_LANGUAGES,
    normalizeShopwareSetupBlock,
};
