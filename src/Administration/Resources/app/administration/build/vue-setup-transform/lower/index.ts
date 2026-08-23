/**
 * @sw-package framework
 */

/**
 * Selects the mode-specific lowering stage for an analyzed Shopware setup block.
 *
 * The analyzer hands this module a normalized semantic model; the lowerers turn that model into source
 * edits - which ranges of the SFC they replace, and with which chunks. Rendering to text happens once,
 * in the top-level transform, so original ranges stay addressable for later sourcemaps.
 *
 * Every edit a lowering mode needs comes from here, including edits outside the script block: an
 * override with no `<template>` gets one generated, because needing it is a fact about how that mode
 * lowers, not about the SFC.
 */

import { buildBaseScript } from './base';
import { buildOverrideScript } from './override';
import type { ShopwareSetupScriptAnalysis } from '../script-analyzer';
import type { SourceEdit } from '../source-edits/apply-source-edits';
import type { TemplateAnalysis } from '../template-analyzer';
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
    // The template pass runs after script analysis, so its result arrives as its own argument rather
    // than folded onto `analysis`. Each mode reads only the parts its own template shape produces.
    templateAnalysis: TemplateAnalysis,
): SourceEdit[] {
    return analysis.mode === 'base'
        ? buildBaseScript(block, analysis, templateAnalysis)
        : buildOverrideScript(block, analysis, templateAnalysis);
}

/**
 * @private
 */
export { lowerShopwareSetupBlock };
