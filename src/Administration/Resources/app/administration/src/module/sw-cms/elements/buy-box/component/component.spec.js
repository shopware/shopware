/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const productMock = {
    name: 'Lorem Ipsum dolor',
    productNumber: '1234',
    minPurchase: 1,
    deliveryTime: {
        name: '1-3 days',
    },
    price: [
        { gross: 100 },
    ],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-buy-box', {
        sync: true,
    }), {
        props: {
            element: {
                data: {},
                config: {},
            },
            defaultConfig: {
                alignment: {
                    value: null,
                },
            },
        },
        global: {
            stubs: {
                'sw-block-field': true,
                'sw-icon': true,
            },
            provide: {
                cmsService: Shopware.Service('cmsService'),
            },
        },
    });
}

describe('module/sw-cms/elements/buy-box/component', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/buy-box');
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should show skeleton if page type is product page', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_detail',
        });

        expect((await createWrapper()).get('.sw-cms-el-buy-box__skeleton')).toBeTruthy();
    });

    it('should show dummy data initially if page type is not product page and no product config', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-cms-el-buy-box__content')).toBeTruthy();
        expect(wrapper.get('.sw-cms-el-buy-box__price')).toBeTruthy();
    });

    it('should show product data if page type is not product page', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                data: {
                    product: productMock,
                },
                config: {},
            },
        });

        wrapper.get('.sw-cms-el-buy-box__content');
        expect(wrapper.get('.sw-cms-el-buy-box__price').text()).toBe('€100.00');
    });

    it('should show current demo data if mapping entity is product', async () => {
        Shopware.Store.get('cmsPage').setCurrentMappingEntity('product');
        Shopware.Store.get('cmsPage').setCurrentDemoEntity(productMock);
        const wrapper = await createWrapper();

        wrapper.get('.sw-cms-el-buy-box__content');
        expect(wrapper.get('.sw-cms-el-buy-box__price').text()).toBe('€100.00');
    });

    it('should show dummy data initially if mapping entity is not product', async () => {
        Shopware.Store.get('cmsPage').setCurrentMappingEntity(null);
        Shopware.Store.get('cmsPage').setCurrentDemoEntity(productMock);
        const wrapper = await createWrapper();

        wrapper.get('.sw-cms-el-buy-box__content');
        expect(wrapper.get('.sw-cms-el-buy-box__price').text()).toBe('€0.00');
    });

    it('alignStyle provided by element config is used', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            element: {
                data: {
                    product: productMock,
                },
                config: {
                    alignment: {
                        value: 'center',
                    },
                },
            },
        });

        expect(wrapper.get('.sw-cms-el-buy-box').attributes('style')).toBe('justify-content: center;');
    });

    it('computed product falls back to dummy data if no product or demo config is available', async () => {
        Shopware.Store.get('cmsPage').setCurrentDemoEntity(null);
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                data: null,
                config: {},
            },
        });

        expect(wrapper.vm.product).toStrictEqual({
            name: 'Lorem Ipsum dolor',
            productNumber: 'XXXXXX',
            minPurchase: 1,
            deliveryTime: {
                name: '1-3 days',
            },
            price: [
                { gross: 0.00 },
            ],
        });
    });
});
