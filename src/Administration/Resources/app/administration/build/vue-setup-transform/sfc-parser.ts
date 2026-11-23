/**
 * @sw-package framework
 */

/**
 * Parses Vue SFCs and selects files that participate in the Shopware setup transform.
 *
 * Every `.vue` file is a Shopware setup component: the only escape hatch is a Vue parser error, which
 * Vue itself reports with better context. Everything else that is not a valid Shopware setup SFC becomes
 * a `ShopwareSetupTransformError` diagnostic with a source offset.
 */

import { parse as parseWithVue } from '@vue/compiler-sfc';
import {
    inferShopwareSetupFromFilename,
    normalizeShopwareSetupBlock,
    type ShopwareSetupBlock,
    type ShopwareSetupMode,
} from './utils/shopware-setup-block';
import { toScriptBlock } from './utils/sfc-script-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

/**
 * Builds the diagnostic for an SFC that has no `<script setup>` block.
 *
 * The two modes fail for the same reason but need different instructions: a base component declares its
 * extension surface with `swDefinePublic()`, an override registers itself with `swDefineOverride()`.
 * Both markers live in `<script setup>`, so a missing block means the file cannot participate at all.
 */
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
 * Reads Vue's SFC descriptor and returns the Shopware setup block every `.vue` file must have.
 *
 * `null` means "not this transform's problem": Vue's own parser already rejected the file. Anything
 * else - a plain `<script>`, a template-only SFC - is a Shopware setup violation and throws, because
 * a `.vue` file that the transform leaves alone would silently be a non-extendable component.
 */
function parseShopwareSetupSfc(source: string, filename = 'anonymous.vue'): ShopwareSetupBlock | null {
    const parsed = parseWithVue(source, { filename });

    if (parsed.errors.length > 0) {
        // Vue already reports SFC parse errors with better context.
        return null;
    }

    if (!parsed.descriptor.scriptSetup) {
        // Hard error rather than a silent pass-through: an SFC without `<script setup>` has no place to
        // put the markers, so it would compile into a component that cannot be extended and cannot be
        // overridden - the one thing the Administration's component model requires of every component.
        // An `.override.vue` fails even more visibly: it registers nothing and the override never runs.
        throw new ShopwareSetupTransformError(missingScriptSetupMessage(inferShopwareSetupFromFilename(filename).mode), 0);
    }

    const scriptSetupBlock = toScriptBlock(parsed.descriptor.scriptSetup, 'scriptSetup');
    const shopwareSetupBlock = normalizeShopwareSetupBlock(scriptSetupBlock, filename);

    const isCodemodModuleScript = parsed.descriptor.script
        ? Object.prototype.hasOwnProperty.call(parsed.descriptor.script.attrs, 'data-sfc-migration-module')
        : false;

    if (parsed.descriptor.script && !isCodemodModuleScript) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block cannot be combined with another <script> block.',
            parsed.descriptor.script.loc.start.offset,
        );
    }

    return {
        ...shopwareSetupBlock,
        template: parsed.descriptor.template
            ? {
                  content: parsed.descriptor.template.content,
                  contentStart: parsed.descriptor.template.loc.start.offset,
              }
            : null,
        // Only the codemod prelude reaches this point - anything else already threw above.
        moduleScript:
            isCodemodModuleScript && parsed.descriptor.script ? toScriptBlock(parsed.descriptor.script, 'script') : null,
    };
}

/**
 * @private
 */
export { parseShopwareSetupSfc };
