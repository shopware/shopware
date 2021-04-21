import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/product-description-reviews/config';

const productMock = {
    name: 'Awesome Product',
    description: 'This product is awesome'
};

function createWrapper() {
    const localVue = createLocalVue();
    return shallowMount(Shopware.Component.build('sw-cms-el-config-product-description-reviews'), {
        localVue,
        sync: false,
        stubs: {
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>'
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-tabs-item': true,
            'sw-entity-single-select': true,
            'sw-alert': true
        },
        provide: {
            cmsService: {
                getCmsBlockRegistry: () => {
                    return {};
                },
                getCmsElementRegistry: () => {
                    return { 'product-description-reviews': {} };
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        get: () => Promise.resolve(productMock),
                        search: () => Promise.resolve(productMock)
                    };
                }
            }
        },
        propsData: {
            element: {
                config: {},
                data: {}
            },
            defaultConfig: {
                product: {
                    value: null
                },
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

describe('src/module/sw-cms/elements/product-description-reviews/config', () => {
    it('should show product selector if page type is not product detail', async () => {
        const wrapper = createWrapper();
        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is product detail', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            cmsPageState: {
                currentPage: {
                    type: 'product_detail'
                }
            }
        });

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });
});
