/**
 * @sw-package framework
 */

import { lowerShopwareSetupBlock } from './lower';
import { analyzeShopwareSetupScript, type ShopwareSetupScriptAnalysis } from './script-analyzer';
import { applySourceEdits, type AppliedSourceEdits } from './source-edits/apply-source-edits';
import { analyzeBaseTemplate, analyzeOverrideTemplate, type TemplateAnalysis } from './template-analyzer';
import { parseShopwareSetupSfc } from './sfc-parser';
import type { ShopwareSetupBlock } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

type ShopwareSetupTransformResult = {
    code: string;
    map: AppliedSourceEdits['map'];
    mode: 'base' | 'override';
    componentName: string;
    filename: string;
};

/**
 * Moves block-relative analyzer errors to the original SFC block start.
 */
function withBlockOffset(error: unknown, block: ShopwareSetupBlock): unknown {
    if (!(error instanceof ShopwareSetupTransformError) || error.index !== 0) {
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
    let templateAnalysis: TemplateAnalysis = {
        edits: [],
        privateBindings: new Set<string>(),
        privateNamespace: null,
    };

    try {
        analysis = analyzeShopwareSetupScript(block.content, {
            mode: block.mode,
            lang: block.lang,
            scriptOffset: block.contentStart,
        });
        if (block.mode === 'base') {
            templateAnalysis = analyzeBaseTemplate(block);
        }

        if (block.mode === 'override') {
            templateAnalysis = analyzeOverrideTemplate(block, analysis);
            analysis.overridePrivateBindings = templateAnalysis.privateBindings;
            analysis.overridePrivateNamespace = templateAnalysis.privateNamespace;
        }

        replacement = lowerShopwareSetupBlock(block, analysis);
    } catch (error) {
        throw withBlockOffset(error, block);
    }

    const transformed = applySourceEdits(source, filename, [
        ...templateAnalysis.edits,
        {
            start: block.start,
            end: block.end,
            replacement: replacement.chunks,
        },
    ]);

    return {
        code: transformed.code,
        map: transformed.map,
        mode: block.mode,
        // Exposed so the build integration can maintain a per-compilation registry and reject two
        // SFCs that resolve to the same extendable component name. Cross-file enforcement lives with
        // the loader/compilation layer; this transform stays a pure per-file step.
        componentName: block.componentName,
        filename,
    };
}

/**
 * Runs the shared transform for callers that only need diagnostics.
 */
function validateShopwareSetupSfc(source: string, filename = 'anonymous.vue'): void {
    transformShopwareSetupSfc(source, filename);
}

export {
    type ShopwareSetupTransformResult,
    ShopwareSetupTransformError,
    transformShopwareSetupSfc,
    validateShopwareSetupSfc,
};
