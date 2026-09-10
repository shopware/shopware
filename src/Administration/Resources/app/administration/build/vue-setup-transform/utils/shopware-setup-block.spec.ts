/**
 * @sw-package framework
 */

import { inferShopwareSetupFromFilename, normalizeShopwareSetupBlock } from './shopware-setup-block';
import type { ScriptBlock } from './sfc-script-block';

describe('build/vue-setup-transform/utils/shopware-setup-block', () => {
    it.each([
        [
            'src/module/sw-example.vue',
            { mode: 'base', componentName: 'sw-example' },
        ],
        [
            'src/module/sw-example.override.vue',
            { mode: 'override', componentName: 'sw-example' },
        ],
        [
            'src/module/sw-example/index.vue',
            { mode: 'base', componentName: 'sw-example' },
        ],
        [
            'src\\module\\sw-example\\index.override.vue',
            { mode: 'override', componentName: 'sw-example' },
        ],
        [
            'src/module/sw-example.vue?vue&type=script&setup=true',
            { mode: 'base', componentName: 'sw-example' },
        ],
    ])('infers Shopware setup mode and component name from %s', (filename, expected) => {
        expect(inferShopwareSetupFromFilename(filename)).toEqual(expected);
    });

    it('normalizes script setup blocks with filename-inferred metadata', () => {
        const baseBlock = createScriptBlock('tsx');
        const overrideBlock = createScriptBlock(null);

        const base = normalizeShopwareSetupBlock(baseBlock, 'sw-example.vue');
        const override = normalizeShopwareSetupBlock(overrideBlock, 'sw-example.override.vue');

        expect(base).toMatchObject({
            mode: 'base',
            componentName: 'sw-example',
            lang: 'tsx',
        });
        expect(override).toMatchObject({
            mode: 'override',
            componentName: 'sw-example',
            lang: null,
        });
    });
});

function createScriptBlock(lang: string | null): ScriptBlock {
    return {
        type: 'scriptSetup',
        contentStart: '<script setup>'.length,
        contentEnd: '<script setup>'.length,
        content: '',
        lang,
    };
}
