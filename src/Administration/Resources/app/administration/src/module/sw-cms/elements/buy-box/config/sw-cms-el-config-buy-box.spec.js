/**
 * @package buyers-experience
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElConfigBuyBox from 'src/module/sw-cms/elements/buy-box/config';

Shopware.Component.register('sw-cms-el-config-buy-box', swCmsElConfigBuyBox);

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
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-cms-el-config-buy-box'), {
        localVue,
        sync: false,
        propsData: {
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
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage',
                    },
                },
            };
        },
        stubs: {
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
            },
            'sw-tabs-item': true,
            'sw-entity-single-select': true,
            'sw-alert': true,
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
    });
}

describe('module/sw-cms/elements/buy-box/config', () => {
    it('should show product selector if page type is not product detail', async () => {
        const wrapper = await createWrapper();
        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is product detail', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
            },
        });

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });
});
