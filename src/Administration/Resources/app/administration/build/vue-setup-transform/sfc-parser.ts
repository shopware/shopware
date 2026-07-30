/**
 * @sw-package framework
 */

/**
 * Parses Vue SFCs and selects files that participate in the Shopware setup transform.
 *
 * This boundary deliberately returns `null` for ordinary SFCs and Vue parser errors, while Shopware
 * setup-specific violations become `ShopwareSetupTransformError` diagnostics with source offsets.
 */

import { parse as parseWithVue } from '@vue/compiler-sfc';
import {
    inferShopwareSetupFromFilename,
    normalizeShopwareSetupBlock,
    type ShopwareSetupBlock,
} from './utils/shopware-setup-block';
import { toScriptBlock } from './utils/sfc-script-block';
import { ShopwareSetupTransformError } from './utils/transform-error';

/**
 * Reads Vue's SFC descriptor and returns only blocks that use Shopware setup mode.
 */
function parseShopwareSetupSfc(source: string, filename = 'anonymous.vue'): ShopwareSetupBlock | null {
    const parsed = parseWithVue(source, { filename });

    if (parsed.errors.length > 0) {
        // Vue already reports SFC parse errors with better context.
        return null;
    }

    if (!parsed.descriptor.scriptSetup) {
        // An `.override.vue` filename is an explicit declaration of intent, and an override registers
        // itself from its `<script setup>` body - so without that block the file silently registers
        // nothing at all. A plain `.vue` without `<script setup>` is just an ordinary Vue SFC and is
        // left alone, but here the author asked for an override and would not get one.
        if (inferShopwareSetupFromFilename(filename).mode === 'override') {
            throw new ShopwareSetupTransformError(
                'An override component needs a <script setup> block to register its override. Add ' +
                    '<script setup> with swDefineOverride({ ... }) - pass an empty object for a ' +
                    'template-only override.',
                0,
            );
        }

        return null;
    }

    const scriptSetupBlock = toScriptBlock(source, parsed.descriptor.scriptSetup, 'scriptSetup');
    const shopwareSetupBlock = normalizeShopwareSetupBlock(scriptSetupBlock, filename);

    if (parsed.descriptor.script) {
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
    };
}

/**
 * @private
 */
export { parseShopwareSetupSfc };
