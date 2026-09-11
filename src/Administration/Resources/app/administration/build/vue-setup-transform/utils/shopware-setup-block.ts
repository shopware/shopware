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
 *
 * `moduleScript` is the one plain `<script>` an SFC may carry beside the setup block - the migration
 * codemod's `data-sfc-migration-module` prelude. Lowering needs it because Vue allows no second plain
 * script block, so generated module-eval code has to go inside this one when it exists.
 */
type ShopwareSetupBlock = ScriptBlock & {
    mode: ShopwareSetupMode;
    componentName: string;
    lang: string | null;
    template: ShopwareSetupTemplate | null;
    moduleScript: ScriptBlock | null;
};

type InferredShopwareSetup = {
    mode: ShopwareSetupMode;
    componentName: string;
};

/** `sw-thing.vue?vue&type=script` -> `sw-thing.vue` */
function stripFilenameQuery(filename: string): string {
    // `String.split(_, 1)` always yields at least one element, so `[0]` is never undefined here.
    return filename.split(/[?#]/, 1)[0];
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
    // The mode already decided which suffix this file carries; reuse it instead of re-testing.
    const suffix = mode === 'override' ? '.override.vue' : '.vue';
    const componentName = (() => {
        if (file === `index${suffix}`) {
            return parentDirectoryName(filename);
        }

        if (file.endsWith(suffix)) {
            return file.slice(0, -suffix.length);
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
): Omit<ShopwareSetupBlock, 'template' | 'moduleScript'> {
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
