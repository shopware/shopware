/**
 * @sw-package discovery
 */
import { runCmsElementRegistryTest } from 'src/module/sw-cms/test-utils';

describe('src/module/sw-cms/elements/buy-box', () => {
    runCmsElementRegistryTest({
        import: 'src/module/sw-cms/elements/buy-box',
        name: 'buy-box',
        component: 'sw-cms-el-buy-box',
        config: 'sw-cms-el-config-buy-box',
        preview: 'sw-cms-el-preview-buy-box',
    });

    it('registers the product criteria with the deliveryTime association', async () => {
        await import('src/module/sw-cms/elements/buy-box');

        const cmsService = Shopware.Service('cmsService');
        const elementConfig = cmsService.getCmsElementConfigByName('buy-box');

        expect(elementConfig.defaultConfig.product.entity.criteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    association: 'deliveryTime',
                }),
            ]),
        );
    });
});
