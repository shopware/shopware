/**
 * @sw-package framework
 */

import { parse as parseWithVue } from '@vue/compiler-sfc';
import type { RootNode } from '@vue/compiler-dom';
import { type ShopwareSetupMode, inferShopwareSetupFromFilename } from './naming';
import { ShopwareSetupTransformError } from './utils/transform-error';

/**
 * The `<script setup>` block of an SFC plus the component identity inferred from its filename. Only the
 * block content is rewritten, so the tags and their attributes stay untouched.
 */
type ShopwareSetupBlock = {
    mode: ShopwareSetupMode;
    componentName: string;
    filename: string;
    source: string;
    content: string;
    contentStart: number;
    contentEnd: number;
    lang: string | null;
    /** Vue's template AST, with offsets into the whole SFC. */
    template: RootNode | null;
};

function missingScriptSetupMessage(mode: ShopwareSetupMode): string {
    if (mode === 'override') {
        return (
            'An override component needs a <script setup> block to register its override. Add ' +
            '<script setup> with swDefineOverride({ ... }) - pass an empty object for a ' +
            'template-only override.'
        );
    }

    return (
        'A Shopware setup component needs a <script setup> block. Every .vue component is extendable, ' +
        'and the extension surface is declared inside <script setup> - add one with ' +
        'swDefinePublic({ ... }) and pass an empty object if no binding is public. The Options API ' +
        '(a plain <script> block) cannot declare one.'
    );
}

/**
 * Returns `null` when Vue's own parser rejects the SFC, since Vue reports that with better context.
 * Every other `.vue` file must be a Shopware setup SFC: one left alone would silently be a component
 * nothing can extend.
 */
function parseShopwareSetupSfc(source: string, filename: string): ShopwareSetupBlock | null {
    const { descriptor, errors } = parseWithVue(source, { filename });

    if (errors.length > 0) {
        return null;
    }

    const { mode, componentName } = inferShopwareSetupFromFilename(filename);
    const { scriptSetup, script, template } = descriptor;

    if (!scriptSetup) {
        throw new ShopwareSetupTransformError(missingScriptSetupMessage(mode), 0);
    }

    if (script) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block cannot be combined with another <script> block.',
            script.loc.start.offset,
        );
    }

    return {
        mode,
        componentName,
        filename,
        source,
        content: scriptSetup.content,
        contentStart: scriptSetup.loc.start.offset,
        contentEnd: scriptSetup.loc.end.offset,
        lang: scriptSetup.lang ?? null,
        template: template?.ast ?? null,
    };
}

/**
 * @private
 */
export { type ShopwareSetupBlock, parseShopwareSetupSfc };
