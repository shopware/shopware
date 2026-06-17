/**
 * @sw-package framework
 */

const { analyzeShopwareSetupScript } = require('./script-analyzer');
const { ShopwareSetupTransformError } = require('./utils/transform-error');

const DIAGNOSTIC_FEATURES = {
    verification: true,
};
const MAX_DIAGNOSTICS = 20;

/**
 * @typedef {object} VueSfcBlock
 * @property {string} content
 * @property {string | undefined} name
 * @property {string | undefined} lang
 * @property {Record<string, string | boolean> | undefined} attrs
 * @property {number | undefined} startTagEnd
 * @property {number | undefined} endTagStart
 * @property {{ start: { offset: number }, end: { offset: number } } | undefined} loc
 *
 * @typedef {object} VueSfcDescriptor
 * @property {VueSfcBlock | undefined} script
 * @property {VueSfcBlock | undefined} scriptSetup
 *
 * @typedef {object} ShopwareSetupVolarDiagnostic
 * @property {string} message
 * @property {VueSfcBlock} block
 * @property {string} sourceName
 * @property {number} offset
 * @property {number} length
 */

/**
 * Escapes text for a TypeScript string literal.
 *
 * @param {string} value
 * @returns {string}
 */
function escapeTypeScriptString(value) {
    return JSON.stringify(value);
}

/**
 * Returns the block-relative source name Volar uses for source maps.
 *
 * @param {VueSfcBlock} block
 * @returns {string}
 */
function getBlockSourceName(block) {
    return block.name ?? 'scriptSetup';
}

/**
 * Returns the absolute source offset where block content starts.
 *
 * @param {VueSfcBlock} block
 * @returns {number}
 */
function getBlockContentStart(block) {
    return block.startTagEnd ?? block.loc?.start.offset ?? 0;
}

/**
 * Returns the Shopware setup mode declared by a parsed script setup block.
 *
 * @param {VueSfcBlock | undefined} block
 * @returns {'base' | 'override' | null}
 */
function getShopwareSetupMode(block) {
    if (!block?.attrs) {
        return null;
    }

    if (Object.hasOwn(block.attrs, 'sw-component')) {
        return 'base';
    }

    if (Object.hasOwn(block.attrs, 'sw-override')) {
        return 'override';
    }

    return null;
}

/**
 * Finds the identifier-like token at the diagnostic offset.
 *
 * @param {VueSfcBlock} block
 * @param {number} offset
 * @returns {{ offset: number, length: number } | null}
 */
function findIdentifierTokenAtOffset(block, offset) {
    const blockOffset = offset - getBlockContentStart(block);
    const token = /^[A-Za-z_$][\w$]*/.exec(block.content.slice(blockOffset));

    if (!token) {
        return null;
    }

    return {
        offset: blockOffset,
        length: token[0].length,
    };
}

/**
 * Replaces the invalid token with a same-length inert identifier so validation
 * can continue and report later errors without shifting source offsets.
 *
 * @param {string} script
 * @param {{ offset: number, length: number }} token
 * @returns {string}
 */
function maskIdentifierToken(script, token) {
    return `${script.slice(0, token.offset)}${'_'.repeat(token.length)}${script.slice(token.offset + token.length)}`;
}

/**
 * Collects validator errors that can be exposed as source-mapped TS diagnostics.
 *
 * @param {string} fileName
 * @param {VueSfcDescriptor} sfc
 * @returns {ShopwareSetupVolarDiagnostic[]}
 */
function collectShopwareSetupVolarDiagnostics(fileName, sfc) {
    const block = sfc.scriptSetup;
    const mode = getShopwareSetupMode(block);

    if (!block || !mode) {
        return [];
    }

    const diagnostics = [];
    let script = block.content;

    while (diagnostics.length < MAX_DIAGNOSTICS) {
        try {
            analyzeShopwareSetupScript(script, {
                mode,
                lang: block.lang ?? null,
                scriptOffset: getBlockContentStart(block),
            });

            return diagnostics;
        } catch (error) {
            if (!(error instanceof ShopwareSetupTransformError)) {
                throw error;
            }

            const token = findIdentifierTokenAtOffset(block, error.index);

            if (!token) {
                return diagnostics;
            }

            diagnostics.push({
                message: error.message,
                block,
                sourceName: getBlockSourceName(block),
                offset: token.offset,
                length: token.length,
            });

            script = maskIdentifierToken(script, token);
        }
    }

    return diagnostics;
}

/**
 * Adds virtual TypeScript that reports a mapped diagnostic for the invalid macro.
 *
 * @param {{ content: unknown[] }} code
 * @param {ShopwareSetupVolarDiagnostic[]} diagnostics
 * @returns {void}
 */
function addDiagnosticVirtualCode(code, diagnostics) {
    diagnostics.forEach((diagnostic, index) => {
        const diagnosticName = `__shopwareSetupDiagnostic_${index}`;
        const diagnosticMessage = `Shopware setup error: ${diagnostic.message}`;
        const sourceText = diagnostic.block.content.slice(
            diagnostic.offset,
            diagnostic.offset + diagnostic.length,
        );

        code.content.push(
            `declare const ${diagnosticName}: (value: { ${escapeTypeScriptString(diagnosticMessage)}: never }) => void;\n`,
            `${diagnosticName}(`,
            [
                sourceText,
                diagnostic.sourceName,
                diagnostic.offset,
                DIAGNOSTIC_FEATURES,
            ],
            ');\n',
        );
    });
}

/**
 * This plugin intentionally does not transform SFC source for Volar.
 *
 * Editor tooling works on original source offsets. Returning generated SFC
 * descriptors from parseSFC/parseSFC2 makes diagnostics and semantic
 * highlighting point at generated offsets instead of the file the developer
 * sees. Shopware setup authoring syntax is already valid Vue syntax once the
 * compile-time macros and injected composables are declared as globals.
 *
 * Build-time syntax validation stays in the shared transform and ESLint rule,
 * and editor diagnostics are appended to Volar's generated script service file.
 *
 * @returns {{ version: 2.2, name: string }}
 */
module.exports = function shopwareSetupVolarPlugin() {
    return {
        version: 2.2,
        name: 'shopware-setup',
        resolveEmbeddedCode(fileName, sfc, code) {
            if (!/^script_(js|jsx|ts|tsx)$/.test(code.id)) {
                return;
            }

            addDiagnosticVirtualCode(code, collectShopwareSetupVolarDiagnostics(fileName, sfc));
        },
    };
};

module.exports._private = {
    collectShopwareSetupVolarDiagnostics,
};
