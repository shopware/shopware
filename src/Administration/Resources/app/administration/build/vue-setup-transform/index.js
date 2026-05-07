/**
 * @sw-package framework
 */

const { parseShopwareSetupSfc } = require('./sfc-parser');
const { analyzeShopwareSetupScript } = require('./script-analyzer');
const { lowerShopwareSetupBlock } = require('./lower');
const { ShopwareSetupTransformError } = require('./utils/transform-error');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 *
 * @typedef {object} ShopwareSetupTransformResult
 * @property {string} code
 * @property {null} map
 * @property {'base' | 'override'} mode
 * @property {string} filename
 */

/**
 * Moves block-relative analyzer errors to the original SFC block start.
 *
 * @param {unknown} error
 * @param {ShopwareSetupBlock} block
 * @returns {unknown}
 */
function withBlockOffset(error, block) {
    if (!(error instanceof ShopwareSetupTransformError) || error.index !== 0) {
        return error;
    }

    return new ShopwareSetupTransformError(error.message, block.start);
}

/**
 * Converts a Shopware setup SFC into plain Vue-compatible code before Vue compiles it.
 *
 * @param {string} source
 * @param {string} [filename]
 * @returns {ShopwareSetupTransformResult | null}
 */
function transformShopwareSetupSfc(source, filename = 'anonymous.vue') {
    const block = parseShopwareSetupSfc(source, filename);

    if (!block) {
        return null;
    }

    let analysis;
    let replacement;

    try {
        analysis = analyzeShopwareSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        replacement = lowerShopwareSetupBlock(block, analysis);
    } catch (error) {
        throw withBlockOffset(error, block);
    }

    return {
        code: `${source.slice(0, block.start)}${replacement}${source.slice(block.end)}`,
        map: null,
        mode: block.mode,
        filename,
    };
}

/**
 * Runs the shared transform for callers that only need diagnostics.
 *
 * @param {string} source
 * @param {string} [filename]
 * @returns {void}
 */
function validateShopwareSetupSfc(source, filename = 'anonymous.vue') {
    transformShopwareSetupSfc(source, filename);
}

module.exports = {
    ShopwareSetupTransformError,
    transformShopwareSetupSfc,
    validateShopwareSetupSfc,
};
