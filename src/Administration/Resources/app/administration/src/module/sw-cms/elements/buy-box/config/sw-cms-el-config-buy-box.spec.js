/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

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
    return mount(await wrapTestComponent('sw-cms-el-config-buy-box', {
        sync: true,
    }), {
        sync: false,
        props: {
            element: {
                data: {},
                config: {},
            },
            defaultConfig: {
                product: {
                    value: null,
                },
                alignment: {
                    value: null,
                },
            },
        },
        global: {
            stubs: {
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                },
                'sw-tabs-item': true,
                'sw-entity-single-select': true,
                'sw-alert': true,
                'sw-product-variant-info': true,
                'sw-select-result': true,
                'sw-select-field': true,
            },
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'buy-box': {} };
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(productMock),
                            search: () => Promise.resolve(productMock),
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-cms/elements/buy-box/config', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({}),
        });
    });

    it('should show product selector if page type is not product detail', async () => {
        const wrapper = await createWrapper();
        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is product detail', async () => {
        const store = Shopware.Store.get('cmsPageState');
        store.currentPage = {
            type: 'product_detail',
        };
        expect(store.currentPage.type).toBe('product_detail');

        const wrapper = await createWrapper();
        expect(wrapper.vm.isProductPage).toBe(true);

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });
});
