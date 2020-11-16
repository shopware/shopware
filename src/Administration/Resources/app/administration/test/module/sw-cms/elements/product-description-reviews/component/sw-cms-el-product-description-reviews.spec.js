import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-description-reviews/component';

function createWrapper() {
    const localVue = createLocalVue();
    return shallowMount(Shopware.Component.build('sw-cms-el-product-description-reviews'), {
        localVue,
        sync: false,
        mocks: {
            $tc: v => v
        },
        provide: {
            feature: {
                isActive: () => true
            },
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return {
                        'product-description-reviews': {
                            defaultData: {}
                        }
                    };
                }
            }
        },
        propsData: {
            element: {
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
                }
            }
        });

        expect(wrapper.find('.sw-cms-el-product-description-reviews__detail-title').text()).toBe('Product information');
    });
});
