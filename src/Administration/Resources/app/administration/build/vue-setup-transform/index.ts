/**
 * @sw-package framework
 */

/**
 * Converts Shopware's native setup SFC dialect into plain Vue SFC source before Vue compilation.
 *
 * This module owns the per-file transform boundary: parse the SFC, analyze script and template
 * semantics, lower the Shopware setup block into source edits, and apply them - while leaving
 * cross-file component-name checks to the build integration. Every edit comes from lowering; nothing
 * generated is decided here.
 */

import { lowerShopwareSetupBlock } from './lower';
import { analyzeShopwareSetupScript } from './script-analyzer';
import { applySourceEdits, type AppliedSourceEdits } from './source-edits/apply-source-edits';
import { analyzeBaseTemplate, analyzeOverrideTemplate } from './template-analyzer';
import { parseShopwareSetupSfc } from './sfc-parser';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';
import { resolveErrorSource } from './utils/error-source';

type ShopwareSetupTransformResult = {
    code: string;
    map: AppliedSourceEdits['map'];
    mode: 'base' | 'override';
    componentName: string;
    filename: string;
    // Static names of the base `<sw-block name="...">` blocks this component owns (empty for overrides).
    // Emitted for a later branch to build a cross-file block-ownership registry.
    ownedBlockNames: string[];
    // Static names of the blocks this override `<sw-block extends="...">` extends (empty for base).
    // The registry's other half, for a later branch to cross-check against the emitted ownership.
    extendedBlockNames: string[];
};

/**
 * Resolves the error's absolute offset against the original SFC. A position-less error is anchored
 * to the script block so Vite and Jest still land in the right file region.
 */
function withAuthorLocation(error: unknown, source: string, filename: string, block: ShopwareSetupBlock | null): unknown {
    if (!(error instanceof ShopwareSetupTransformError)) {
        return error;
    }

    const diagnostic = resolveErrorSource(source, filename, error.index ?? block?.contentStart ?? 0, error.endIndex);
    error.loc = diagnostic.loc;
    // Vite may catch this in the importer; supply the frame so it cannot highlight that file instead.
    error.frame = diagnostic.frame;

    return error;
}

/**
 * Converts a Shopware setup SFC into plain Vue-compatible code before Vue compiles it.
 */
function transformShopwareSetupSfc(source: string, filename = 'anonymous.vue'): ShopwareSetupTransformResult | null {
    let block: ShopwareSetupBlock | null = null;

    try {
        block = parseShopwareSetupSfc(source, filename);

        if (!block) {
            return null;
        }

        const analysis = analyzeShopwareSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        const templateAnalysis =
            analysis.mode === 'base' ? analyzeBaseTemplate(block) : analyzeOverrideTemplate(block, analysis);

        const edits = lowerShopwareSetupBlock(block, analysis, templateAnalysis);
        const transformed = applySourceEdits(source, filename, edits);

        return {
            code: transformed.code,
            map: transformed.map,
            mode: block.mode,
            // Exposed so the build integration can maintain a per-compilation registry and reject two
            // SFCs that resolve to the same extendable component name. Cross-file enforcement lives with
            // the loader/compilation layer; this transform stays a pure per-file step.
            componentName: block.componentName,
            filename,
            ownedBlockNames: templateAnalysis.ownedBlockNames,
            extendedBlockNames: templateAnalysis.extendedBlockNames,
        };
    } catch (error) {
        throw withAuthorLocation(error, source, filename, block);
    }
}

/**
 * Runs the shared transform for callers that only need diagnostics.
 */
function validateShopwareSetupSfc(source: string, filename = 'anonymous.vue'): void {
    transformShopwareSetupSfc(source, filename);
}

/**
 * @private
 */
export {
    type ShopwareSetupTransformResult,
    ShopwareSetupTransformError,
    transformShopwareSetupSfc,
    validateShopwareSetupSfc,
};
