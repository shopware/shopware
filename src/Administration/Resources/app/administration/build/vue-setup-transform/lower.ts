/**
 * @sw-package framework
 */

/**
 * Selects the mode-specific lowering stage for an analyzed Shopware setup block.
 *
 * The analyzer hands this module a normalized semantic model; the lowerers turn that model into
 * source chunks so callers can render code now and preserve original ranges for later sourcemaps.
 */

import { buildBaseScript } from './lower/base';
import { buildOverrideScript } from './lower/override';
import type { ShopwareSetupScriptAnalysis } from './script-analyzer';
import type { SourceChunk } from './source-edits/chunks';
import { render } from './source-edits/render-chunks';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';

/**
 * Contains the generated script as source chunks and as rendered text.
 *
 * Tests assert against `code`, while the top-level SFC transform uses `chunks` to splice the lowered
 * script back into the complete file.
 */
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
    const chunks = block.mode === 'base' ? buildBaseScript(block, analysis) : buildOverrideScript(block, analysis);

    return {
        chunks,
        code: render(chunks, analysis.source, block.contentStart),
    };
}

export { type LoweredShopwareSetupBlock, lowerShopwareSetupBlock };
