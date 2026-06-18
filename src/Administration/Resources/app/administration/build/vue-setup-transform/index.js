/**
 * @sw-package framework
 */

const { parseShopwareSetupSfc } = require('./sfc-parser');
const { analyzeShopwareSetupScript } = require('./script-analyzer');
const { analyzeBaseTemplate, analyzeOverrideTemplate } = require('./template-analyzer');
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
 * Applies non-overlapping source edits from the end of the file to the beginning.
 *
 * @param {string} source
 * @param {{ start: number, end: number, replacement: string }[]} edits
 * @returns {string}
 */
function applySourceEdits(source, edits) {
    return [...edits]
        .sort((a, b) => b.start - a.start)
        .reduce((code, edit) => {
            return `${code.slice(0, edit.start)}${edit.replacement}${code.slice(edit.end)}`;
        }, source);
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
    let templateAnalysis = {
        edits: [],
        privateBindings: new Set(),
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

    const code = applySourceEdits(source, [
        ...templateAnalysis.edits,
        {
            start: block.start,
            end: block.end,
            replacement,
        },
    ]);

    return {
        code,
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
