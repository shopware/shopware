import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-description-reviews/component';

const productMock = {
    name: 'Awesome Product',
    description: 'This product is awesome'
};

function createWrapper() {
    const localVue = createLocalVue();
    return shallowMount(Shopware.Component.build('sw-cms-el-product-description-reviews'), {
        localVue,
        sync: false,
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { 'product-description-reviews': {} };
                }
            }
        },
        propsData: {
            element: {
                config: {},
                data: {}
            },
            defaultConfig: {
                alignment: {
                    value: null
                }
            }
        },
        data() {
            return {
                cmsPageState: {
                    currentPage: {
                        type: 'ladingpage'
                    }
                }
            };
        }
    });
}

describe('src/module/sw-cms/elements/product-description-reviews/component', () => {
    it('should display placeholder when page type is not product page and no product is selected', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail').exists()).toBeTruthy();
    });

    it('should display skeleton when page type is product page and no product is selected', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail'
                }
            }
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__placeholder').exists()).toBeTruthy();
    });

    it('should display data when product is selected', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            element: {
                data: {
                    product: {
                        name: 'Product information',
                        description: 'lorem'
                    }
                },
                config: {}
            }
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Product information');
    });

    it('should show current demo data if mapping entity is product', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail'
                },
                currentMappingEntity: 'product',
                currentDemoEntity: productMock
            }
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Awesome Product');
    });

    it('should show dummy data initially if mapping entity is not product', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'landingpage'
                },
                currentMappingEntity: null,
                currentDemoEntity: productMock
            }
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Product information');
    });
});
