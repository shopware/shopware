/**
 * @package content
 */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import swCmsElBuyBox from 'src/module/sw-cms/elements/buy-box/component';

Shopware.Component.register('sw-cms-el-buy-box', swCmsElBuyBox);

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
    localVue.filter('currency', key => key);

    return shallowMount(await Shopware.Component.build('sw-cms-el-buy-box'), {
        localVue,
        sync: false,
        propsData: {
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
            'sw-block-field': true,
            'sw-icon': true,
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
        },
    });
}

describe('module/sw-cms/elements/buy-box/component', () => {
    it('should show skeleton if page type is product page', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-buy-box__skeleton').exists()).toBeTruthy();
    });

    it('should show dummy data initially if page type is not product page and no product config', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-buy-box__content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-buy-box__price').text()).toBe('0');
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

        expect(wrapper.find('.sw-cms-el-buy-box__content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-buy-box__price').text()).toBe('100');
    });

    it('should show current demo data if mapping entity is product', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
                currentMappingEntity: 'product',
                currentDemoEntity: productMock,
            },
        });

        expect(wrapper.find('.sw-cms-el-buy-box__content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-buy-box__price').text()).toBe('100');
    });

    it('should show dummy data initially if mapping entity is not product', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'landingpage',
                },
                currentMappingEntity: null,
                currentDemoEntity: productMock,
            },
        });

        expect(wrapper.find('.sw-cms-el-buy-box__content').exists()).toBeTruthy();
        expect(wrapper.find('.sw-cms-el-buy-box__price').text()).toBe('0');
    });
});
