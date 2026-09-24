import { extractDataSetIds } from './extract-data-set-ids';

describe('extract-data-set-ids', () => {
    it.each([
        [
            'ids from a multi-line and a single-line call',
            `Shopware.ExtensionAPI.publishData({
                id: 'sw-product-detail__product',
                path: 'product',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({ id: 'sw-product-detail__variants', path: 'v', scope: this });`,
            [
                'sw-product-detail__product',
                'sw-product-detail__variants',
            ],
        ],
        [
            'an id from a vue script setup block',
            `<script setup lang="ts">
            Shopware.ExtensionAPI.publishData({ id: 'sw-native-detail__product', path: 'product' });
            </script>
            <template><div /></template>`,
            ['sw-native-detail__product'],
        ],
        [
            'a double quoted id',
            '.publishData({ id: "sw-quoted__product" })',
            ['sw-quoted__product'],
        ],
    ])('collects %s', (_case, code, expected) => {
        expect(extractDataSetIds(code)).toEqual(expected);
    });

    it.each([
        [
            'a publishData call without an id',
            ".publishData({ path: 'product', scope: this })",
        ],
        [
            'a non literal id',
            '.publishData({ id: dataSetId, path: "product" })',
        ],
        [
            'a spread configuration object',
            '.publishData(dataSetConfig)',
        ],
        [
            'a file without any publishData call',
            "export default { name: 'sw-product-detail' };",
        ],
    ])('ignores %s', (_case, code) => {
        expect(extractDataSetIds(code)).toEqual([]);
    });
});
