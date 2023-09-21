/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils_v3';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const productMock = {
    name: 'Awesome Product',
    description: 'This product is awesome',
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-product-description-reviews', {
        sync: true,
    }), {
        global: {
            provide: {
                cmsService: {
                    getCmsBlockRegistry: () => {
                        return {};
                    },
                    getCmsElementRegistry: () => {
                        return { 'product-description-reviews': {} };
                    },
                },
            },
        },
        props: {
            element: {
                config: {},
                data: {},
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
    });
}

describe('src/module/sw-cms/elements/product-description-reviews/component', () => {
    it('should display placeholder when page type is not product page and no product is selected', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail').exists()).toBeTruthy();
    });

    it('should display skeleton when page type is product page and no product is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail',
                },
            },
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__placeholder').exists()).toBeTruthy();
    });

    it('should display data when product is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            element: {
                data: {
                    product: {
                        name: 'Product information',
                        description: 'lorem',
                    },
                },
                config: {},
            },
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Product information');
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

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Awesome Product');
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

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Product information');
    });
});
