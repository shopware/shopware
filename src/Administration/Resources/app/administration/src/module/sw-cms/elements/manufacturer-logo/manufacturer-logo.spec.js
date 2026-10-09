/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/manufacturer-logo', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/manufacturer-logo',
        name: 'manufacturer-logo',
        component: 'sw-cms-el-manufacturer-logo',
        config: 'sw-cms-el-config-manufacturer-logo',
        preview: 'sw-cms-el-preview-manufacturer-logo',
    });

    it('is only allowed on the product detail page', () => {
        const elementConfig = Shopware.Service('cmsService').getCmsElementConfigByName('manufacturer-logo');

        expect(elementConfig.allowedPageTypes).toEqual(['product_detail']);
    });
});
