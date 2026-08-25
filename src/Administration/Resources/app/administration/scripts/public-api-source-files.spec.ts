import { createSourceFileFilter } from './public-api-source-files';

const isSourceFile = createSourceFileFilter(/^.*\.(html\.twig|vue|ts)$/);

describe('createSourceFileFilter', () => {
    it.each([
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.html.twig',
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.vue',
        'src/module/sw-product/page/sw-product-detail/index.ts',
    ])('accepts %s', (filePath) => {
        expect(isSourceFile(filePath)).toBe(true);
    });

    it.each([
        'src/module/sw-product/page/sw-product-detail/sw-product-detail.scss',
        'src/module/sw-product/page/sw-product-detail/snippet/en-GB.json',
    ])('rejects %s, which the extension filter excludes', (filePath) => {
        expect(isSourceFile(filePath)).toBe(false);
    });

    it.each([
        'src/app/adapter/_mocks_/sw-jest-transform-fixture.vue',
        'src/app/adapter/_mocks_/sw-fixture.html.twig',
        'scripts/codemods/sfc-migration/__fixtures__/component.vue',
        'src/module/sw-product/sw-product.spec/fixture.vue',
        'src/module/sw-product/sw-product.spec/data-sets.spec.ts',
    ])('rejects %s, which is test scaffolding', (filePath) => {
        expect(isSourceFile(filePath)).toBe(false);
    });
});
