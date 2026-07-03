/**
 * @sw-package framework
 */

import type { ScriptBlock } from './sfc-script-block';

type ShopwareSetupMode = 'base' | 'override';

type ShopwareSetupTemplate = {
    content: string;
    contentStart: number;
};

type ShopwareSetupBlock = ScriptBlock & {
    mode: ShopwareSetupMode;
    componentName: string;
    lang: string | null;
    template: ShopwareSetupTemplate | null;
    filename: string;
};

type InferredShopwareSetup = {
    mode: ShopwareSetupMode;
    componentName: string;
};

function stripFilenameQuery(filename: string): string {
    return filename.split(/[?#]/, 1)[0] ?? filename;
}

function basename(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    return parts[parts.length - 1] ?? filename;
}

function parentDirectoryName(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    if (parts.length < 2) {
        return basename(filename);
    }

    return parts[parts.length - 2] ?? basename(filename);
}

/**
 * Infers Shopware setup mode and component name from the SFC filename.
 */
function inferShopwareSetupFromFilename(filename: string): InferredShopwareSetup {
    const file = basename(filename);
    const mode: ShopwareSetupMode = file.endsWith('.override.vue') ? 'override' : 'base';
    const componentName = (() => {
        if (file === 'index.vue' || file === 'index.override.vue') {
            return parentDirectoryName(filename);
        }

        if (file.endsWith('.override.vue')) {
            return file.slice(0, -'.override.vue'.length);
        }

        if (file.endsWith('.vue')) {
            return file.slice(0, -'.vue'.length);
        }

        return file;
    })();

    return {
        mode,
        componentName,
    };
}

/**
 * Turns a generic script setup block into the filename-inferred base/override Shopware mode.
 */
function normalizeShopwareSetupBlock(
    block: ScriptBlock,
    filename: string,
): Omit<ShopwareSetupBlock, 'template' | 'filename'> {
    const inferred = inferShopwareSetupFromFilename(filename);

    return {
        ...block,
        mode: inferred.mode,
        componentName: inferred.componentName,
        lang: block.lang,
    };
}

export {
    type ShopwareSetupBlock,
    type ShopwareSetupMode,
    type ShopwareSetupTemplate,
    inferShopwareSetupFromFilename,
    normalizeShopwareSetupBlock,
};
