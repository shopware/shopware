import { isTemplateSourceFile, isDataSetSourceFile } from './public-api-source-files';

describe('isTemplateSourceFile', () => {
    it.each([
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig',
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.vue',
    ])('accepts %s', (filePath) => {
        expect(isTemplateSourceFile(filePath)).toBe(true);
    });

    it.each([
        'src/module/sw-product/page/sw-product-detail/index.ts',
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.scss',
        'src/module/sw-product/page/sw-product-detail/snippet/en-GB.json',
    ])('rejects %s, which the extension filter excludes', (filePath) => {
        expect(isTemplateSourceFile(filePath)).toBe(false);
    });

    it.each([
        'src/app/adapter/_mocks_/sw-jest-transform-fixture.vue',
        'src/app/adapter/_mocks_/sw-fixture.html.twig',
        'scripts/codemods/sfc-migration/__fixtures__/component.vue',
        'src/module/sw-product/sw-product.spec/fixture.vue',
    ])('rejects %s, which is test scaffolding', (filePath) => {
        expect(isTemplateSourceFile(filePath)).toBe(false);
    });
});

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

    it.each([
        'src/app/adapter/_mocks_/sw-fixture.ts',
        'scripts/codemods/sfc-migration/__fixtures__/component.vue',
        'src/module/sw-product/sw-product.spec/data-sets.spec.ts',
    ])('rejects %s, which is test scaffolding', (filePath) => {
        expect(isDataSetSourceFile(filePath)).toBe(false);
    });
});
