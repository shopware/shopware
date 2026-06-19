/**
 * @sw-package framework
 */

import { analyzeShopwareSetupScript } from './script-analyzer';
import type { ShopwareSetupMode } from './utils/shopware-setup-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

const DIAGNOSTIC_FEATURES = {
    verification: true,
};
const MAX_DIAGNOSTICS = 20;

type VueSfcBlock = {
    content: string,
    setup?: string | boolean,
    type?: string,
    name?: string,
    lang?: string | null,
    attrs?: Record<string, string | boolean>,
    startTagEnd?: number,
    endTagStart?: number,
    loc?: { start: { offset: number }, end: { offset: number } },
};

type VueSfcDescriptor = {
    script?: VueSfcBlock | null,
    scriptSetup?: VueSfcBlock | null,
};

type ShopwareSetupVolarDiagnostic = {
    message: string,
    block: VueSfcBlock,
    sourceName: string,
    offset: number,
    length: number,
};

type VolarEmbeddedCode = {
    id: string,
    content: unknown[],
};

type ShopwareSetupVolarPlugin = {
    version: 2.2,
    name: string,
    resolveEmbeddedCode(fileName: string, sfc: VueSfcDescriptor, code: VolarEmbeddedCode): void,
};

/**
 * Escapes text for a TypeScript string literal.
 */
function escapeTypeScriptString(value: string): string {
    return JSON.stringify(value);
}

/**
 * Returns the block-relative source name Volar uses for source maps.
 */
function getBlockSourceName(block: VueSfcBlock): string {
    return block.name ?? (block.setup ? 'scriptSetup' : 'script');
}

/**
 * Returns the absolute source offset where block content starts.
 */
function getBlockContentStart(block: VueSfcBlock): number {
    return block.startTagEnd ?? block.loc?.start.offset ?? 0;
}

/**
 * Returns the Shopware setup mode declared by a parsed script setup block.
 */
function getShopwareSetupMode(block: VueSfcBlock | null | undefined): ShopwareSetupMode | null {
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
 */
function findIdentifierTokenAtOffset(block: VueSfcBlock, offset: number): { offset: number, length: number } | null {
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
 */
function maskIdentifierToken(script: string, token: { offset: number, length: number }): string {
    return `${script.slice(0, token.offset)}${'_'.repeat(token.length)}${script.slice(token.offset + token.length)}`;
}

/**
 * Returns a mapped diagnostic for descriptor-level script block errors.
 */
function createBlockDiagnostic(message: string, block: VueSfcBlock): ShopwareSetupVolarDiagnostic | null {
    const token = /\S+/u.exec(block.content);

    if (!token) {
        return null;
    }

    return {
        message,
        block,
        sourceName: getBlockSourceName(block),
        offset: token.index,
        length: token[0].length,
    };
}

/**
 * Collects validator errors that can be exposed as source-mapped TS diagnostics.
 */
function collectShopwareSetupVolarDiagnostics(
    fileName: string,
    sfc: VueSfcDescriptor,
): ShopwareSetupVolarDiagnostic[] {
    const block = sfc.scriptSetup;
    const mode = getShopwareSetupMode(block);

    if (!block || !mode) {
        return [];
    }

    const diagnostics: ShopwareSetupVolarDiagnostic[] = [];

    if (sfc.script) {
        const scriptDiagnostic = createBlockDiagnostic(
            'A Shopware setup block cannot be combined with another <script> block',
            sfc.script,
        );

        if (scriptDiagnostic) {
            diagnostics.push(scriptDiagnostic);
        }
    }

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
 */
function addDiagnosticVirtualCode(code: VolarEmbeddedCode, diagnostics: ShopwareSetupVolarDiagnostic[]): void {
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
 */
function shopwareSetupVolarPlugin(): ShopwareSetupVolarPlugin {
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
}

module.exports = shopwareSetupVolarPlugin;

module.exports._private = {
    collectShopwareSetupVolarDiagnostics,
};

export default shopwareSetupVolarPlugin;
