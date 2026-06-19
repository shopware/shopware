/**
 * @sw-package framework
 */

/**
 * Converts Shopware's native setup SFC dialect into plain Vue SFC source before Vue compilation.
 *
 * This module owns the per-file transform boundary: parse the SFC, analyze script/template semantics,
 * lower the Shopware setup block, and apply all source edits while leaving cross-file component-name
 * checks to the build integration.
 */

import { lowerShopwareSetupBlock } from './lower';
import { analyzeShopwareSetupScript, type ShopwareSetupScriptAnalysis } from './script-analyzer';
import { applySourceEdits, type AppliedSourceEdits } from './source-edits/apply-source-edits';
import {
    analyzeBaseTemplate,
    analyzeOverrideTemplate,
    emptyTemplateAnalysis,
    type TemplateAnalysis,
} from './template-analyzer';
import { parseShopwareSetupSfc } from './sfc-parser';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

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
 * Moves block-relative analyzer errors to the original SFC block start.
 */
function withBlockOffset(error: unknown, block: ShopwareSetupBlock): unknown {
    if (!(error instanceof ShopwareSetupTransformError) || error.index !== null) {
        return error;
    }

    return new ShopwareSetupTransformError(error.message, block.start);
}

/**
 * Converts a Shopware setup SFC into plain Vue-compatible code before Vue compiles it.
 */
function transformShopwareSetupSfc(source: string, filename = 'anonymous.vue'): ShopwareSetupTransformResult | null {
    const block = parseShopwareSetupSfc(source, filename);

    if (!block) {
        return null;
    }

    let analysis: ShopwareSetupScriptAnalysis;
    let replacement: ReturnType<typeof lowerShopwareSetupBlock>;
    let templateAnalysis: TemplateAnalysis = emptyTemplateAnalysis();

    try {
        analysis = analyzeShopwareSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        templateAnalysis = analysis.mode === 'base' ? analyzeBaseTemplate(block) : analyzeOverrideTemplate(block, analysis);

        // Which override locals must be forwarded is only known after the template pass, so it is passed
        // straight into lowering (empty in base mode) rather than written back onto `analysis`.
        replacement = lowerShopwareSetupBlock(block, analysis, templateAnalysis.privateBindings);
    } catch (error) {
        throw withBlockOffset(error, block);
    }

    // A template-less override still needs to render (a comment node is enough): the hidden override
    // component must mount so its setup registers the override callback, and Vue warns about
    // components without a template or render function.
    const registrationTemplateEdits =
        block.mode === 'override' && !block.template
            ? [
                  {
                      start: 0,
                      end: 0,
                      replacement: '<template><!-- Shopware override registration component --></template>\n',
                  },
              ]
            : [];

    const transformed = applySourceEdits(
        source,
        filename,
        [
            ...registrationTemplateEdits,
            ...templateAnalysis.edits,
            {
                start: block.start,
                end: block.end,
                replacement,
            },
        ],
        analysis.templateLiteralRanges,
    );

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
