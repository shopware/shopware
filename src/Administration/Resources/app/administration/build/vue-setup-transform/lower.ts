/**
 * @sw-package framework
 */

import { buildBaseScript } from './lower/base';
import type { ShopwareSetupScriptAnalysis } from './script-analyzer';
import type { SourceChunk } from './source-edits/chunks';
import { render } from './source-edits/render-chunks';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';

type LoweredShopwareSetupBlock = {
    chunks: SourceChunk[];
    code: string;
};

/**
 * Dispatches to the mode-specific lowering path after shared analysis has completed.
 */
function lowerShopwareSetupBlock(
    block: ShopwareSetupBlock,
    analysis: ShopwareSetupScriptAnalysis,
): LoweredShopwareSetupBlock {
    const chunks = buildBaseScript(block, analysis);

    return {
        chunks,
        code: render(chunks, analysis.source, block.contentStart),
    };
}

export { type LoweredShopwareSetupBlock, lowerShopwareSetupBlock };
