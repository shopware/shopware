/**
 * @sw-package framework
 */

const { buildBaseScript } = require('./lower/base');
const { buildOverrideScript } = require('./lower/override');
const { render } = require('./source-edits/render-chunks');

/**
 * @typedef {import('./utils/shopware-setup-block').ShopwareSetupBlock} ShopwareSetupBlock
 * @typedef {import('./script-analyzer').ShopwareSetupScriptAnalysis} ShopwareSetupScriptAnalysis
 */

/**
 * Dispatches to the mode-specific lowering path after shared analysis has completed.
 *
 * @param {ShopwareSetupBlock} block
 * @param {ShopwareSetupScriptAnalysis} analysis
 * @returns {{ chunks: import('./source-edits/chunks').SourceChunk[], code: string }}
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
