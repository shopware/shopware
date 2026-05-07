/**
 * @sw-package framework
 */

const { parse: parseWithVue } = require('@vue/compiler-sfc');
const { normalizeShopwareSetupBlock, SUPPORTED_LANGUAGES } = require('./utils/shopware-setup-block');
const { toScriptBlock } = require('./utils/sfc-script-block');
const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 */

/**
 * Reads Vue's SFC descriptor and returns only blocks that use Shopware setup mode.
 *
 * @param {string} source
 * @param {string} [filename]
 * @returns {ShopwareSetupBlock | null}
 */
function parseShopwareSetupSfc(source, filename = 'anonymous.vue') {
    const parsed = parseWithVue(source, { filename });

    if (parsed.errors.length > 0) {
        // Vue already reports SFC parse errors with better context.
        return null;
    }

    if (!parsed.descriptor.scriptSetup) {
        return null;
    }

    const scriptSetupBlock = toScriptBlock(source, parsed.descriptor.scriptSetup, 'scriptSetup');
    const shopwareSetupBlock = normalizeShopwareSetupBlock(scriptSetupBlock);

    if (!shopwareSetupBlock) {
        return null;
    }

    if (parsed.descriptor.script) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block cannot be combined with another <script> block',
            parsed.descriptor.script.loc.start.offset,
        );
    }

    return shopwareSetupBlock;
}

module.exports = {
    SUPPORTED_LANGUAGES,
    parseShopwareSetupSfc,
};
