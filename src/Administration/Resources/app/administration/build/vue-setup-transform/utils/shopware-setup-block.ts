/**
 * @sw-package framework
 */

/**
 * Normalizes parsed SFC script blocks into Shopware setup metadata.
 *
 * The transform derives base/override mode and component name from the filename convention so later
 * stages can work with a single block shape instead of repeating path parsing.
 */

import type { ScriptBlock } from './sfc-script-block';

type ShopwareSetupMode = 'base' | 'override';

type ShopwareSetupTemplate = {
    content: string;
    contentStart: number;
};

/**
 * Represents a `<script setup>` block plus the Shopware component identity inferred for it.
 *
 * Base files use `<name>.vue` or `index.vue`; override files use `<name>.override.vue` or
 * `index.override.vue`. The component name is what runtime registration and overrides share.
 */
type ShopwareSetupBlock = ScriptBlock & {
    mode: ShopwareSetupMode;
    componentName: string;
    lang: string | null;
    template: ShopwareSetupTemplate | null;
};

type InferredShopwareSetup = {
    mode: ShopwareSetupMode;
    componentName: string;
};

/** `sw-thing.vue?vue&type=script` -> `sw-thing.vue` */
function stripFilenameQuery(filename: string): string {
    return filename.split(/[?#]/, 1)[0] ?? filename;
}

/** `src/app/sw-thing.vue` -> `sw-thing.vue` */
function basename(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    return parts[parts.length - 1] ?? filename;
}

/** `src/app/sw-thing/index.vue` -> `sw-thing` */
function parentDirectoryName(filename: string): string {
    const parts = stripFilenameQuery(filename).replace(/\\/g, '/').split('/').filter(Boolean);

    if (parts.length < 2) {
        return basename(filename);
    }

    return parts[parts.length - 2] ?? basename(filename);
}

/**
 * Infers Shopware setup mode and component name from the SFC filename.
 *
 * - `sw-thing.vue` / `sw-thing/index.vue` -> base component `sw-thing`
 * - `sw-thing.override.vue` / `sw-thing/index.override.vue` -> override of `sw-thing`
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
function normalizeShopwareSetupBlock(block: ScriptBlock, filename: string): Omit<ShopwareSetupBlock, 'template'> {
    const inferred = inferShopwareSetupFromFilename(filename);

    return {
        ...block,
        mode: inferred.mode,
        componentName: inferred.componentName,
        lang: block.lang,
    };
}

/**
 * @private
 */
export {
    type ShopwareSetupBlock,
    type ShopwareSetupMode,
    type ShopwareSetupTemplate,
    inferShopwareSetupFromFilename,
    normalizeShopwareSetupBlock,
};
