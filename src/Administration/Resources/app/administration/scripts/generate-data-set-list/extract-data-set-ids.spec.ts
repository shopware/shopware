import fs from 'fs';
import { extractDataSetIds, isDataSetSourceFile } from './extract-data-set-ids';

jest.mock('fs');

/**
 * Feeds `extractDataSetIds` file contents by path and returns the data set ids it found.
 */
function extractFrom(files: Record<string, string>): string[] {
    jest.spyOn(fs, 'readFileSync').mockImplementation((filePath) => files[filePath as string]);

    return extractDataSetIds(Object.keys(files));
}

describe('extract-data-set-ids', () => {
    it.each([
        [
            'ids from a multi-line and a single-line call in a ts file',
            {
                'a.ts': `Shopware.ExtensionAPI.publishData({
                        id: 'sw-product-detail__product',
                        path: 'product',
                        scope: this,
                    });
                    Shopware.ExtensionAPI.publishData({ id: 'sw-product-detail__variants', path: 'v', scope: this });`,
            },
            [
                'sw-product-detail__product',
                'sw-product-detail__variants',
            ],
        ],
        [
            'an id from a vue script setup block',
            {
                'a.vue': `<script setup lang="ts">
                    Shopware.ExtensionAPI.publishData({ id: 'sw-native-detail__product', path: 'product' });
                    </script>
                    <template><div /></template>`,
            },
            ['sw-native-detail__product'],
        ],
        [
            'a double quoted id',
            { 'a.ts': '.publishData({ id: "sw-quoted__product" })' },
            ['sw-quoted__product'],
        ],
        [
            'both dialects from one scan',
            {
                'a.ts': ".publishData({ id: 'sw-legacy__product' })",
                'b.vue': "<script setup>.publishData({ id: 'sw-native__product' })</script>",
            },
            [
                'sw-legacy__product',
                'sw-native__product',
            ],
        ],
    ])('collects %s', (_case, files, expected) => {
        expect(extractFrom(files)).toEqual(expected);
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
        expect(extractFrom({ 'a.ts': code })).toEqual([]);
    });

    // Only the lookbehinds, which sit before the extension alternation and are the fiddly part of
    // this filter. The fixture-path half is covered once in `scripts/public-api-source-files.spec.ts`.
    describe('isDataSetSourceFile', () => {
        it.each([
            'src/module/sw-product/page/sw-product-detail/index.ts',
            'src/module/sw-product/page/sw-product-detail/index.js',
            'src/module/sw-product/page/sw-product-detail/sw-product-detail.vue',
        ])('accepts %s', (filePath) => {
            expect(isDataSetSourceFile(filePath)).toBe(true);
        });

        it.each([
            'src/module/sw-product/page/sw-product-detail/index.spec.ts',
            'src/module/sw-product/page/sw-product-detail/sw-product-detail.spec.vue',
            'src/module/sw-product/acl/index.ts',
            'src/global.types.d.ts',
            'src/app/plugin/something.vue2.ts',
            'src/module/sw-product/sw-product.html.twig',
        ])('rejects %s', (filePath) => {
            expect(isDataSetSourceFile(filePath)).toBe(false);
        });
    });
});
