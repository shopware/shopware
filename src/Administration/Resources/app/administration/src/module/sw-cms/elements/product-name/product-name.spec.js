/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/product-name', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/product-name',
        name: 'product-name',
        component: 'sw-cms-el-product-name',
        config: 'sw-cms-el-config-product-name',
        preview: 'sw-cms-el-preview-product-name',
    });

    it('is only allowed on the product detail page', () => {
        const elementConfig = Shopware.Service('cmsService').getCmsElementConfigByName('product-name');

        expect(elementConfig.allowedPageTypes).toEqual(['product_detail']);
    });
});
