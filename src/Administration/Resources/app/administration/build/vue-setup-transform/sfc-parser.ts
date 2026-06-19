/**
 * @sw-package framework
 */

import { parse as parseWithVue } from '@vue/compiler-sfc';
import {
    SUPPORTED_LANGUAGES,
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
        return null;
    }

    const scriptSetupBlock = toScriptBlock(source, parsed.descriptor.scriptSetup, 'scriptSetup');
    const shopwareSetupBlock = normalizeShopwareSetupBlock(scriptSetupBlock);

    if (!shopwareSetupBlock) {
        return null;
    }

    if (parsed.descriptor.script) {
        throw new ShopwareSetupTransformError(
            'A Shopware setup block cannot be combined with another <script> block',
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
        filename,
    };
}

module.exports = {
    SUPPORTED_LANGUAGES,
    parseShopwareSetupSfc,
};

export {
    SUPPORTED_LANGUAGES,
    parseShopwareSetupSfc,
};
