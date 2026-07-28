/**
 * @sw-package framework
 */

/**
 * Selects the mode-specific lowering stage for an analyzed Shopware setup block.
 *
 * The analyzer hands this module a normalized semantic model; the lowerers turn that model into
 * source chunks, which the top-level transform splices back into the complete SFC. Rendering to text
 * happens once, there - so original ranges stay addressable for later sourcemaps.
 */

import { buildBaseScript } from './base';
import { buildOverrideScript } from './override';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { SourceChunk } from '../source-edits/chunks';
import type { ShopwareSetupBlock } from '../utils/shopware-setup-block';

/**
 * Dispatches to the mode-specific lowering path after shared analysis has completed.
 *
 * Narrowing on `analysis.mode` (rather than `block.mode`) is what gives each lowerer a parameter type
 * carrying only the fields its strategy actually produces.
 */
function lowerShopwareSetupBlock(
    block: ShopwareSetupBlock,
    analysis: ShopwareSetupScriptAnalysis,
    // Which override-local bindings the template forwards - known only after template analysis, so it
    // arrives here rather than on `analysis`. Always empty in base mode.
    overridePrivateBindings: Set<string>,
): SourceChunk[] {
    return analysis.mode === 'base'
        ? buildBaseScript(block, analysis)
        : buildOverrideScript(block, analysis, overridePrivateBindings);
}

/**
 * @private
 */
export { lowerShopwareSetupBlock };
