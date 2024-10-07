/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const productMock = {
    name: 'Awesome Product',
    description: 'This product is awesome',
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-el-config-product-description-reviews', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
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
                        return { 'product-description-reviews': {} };
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
        props: {
            element: {
                config: {},
                data: {},
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
    });
}

describe('src/module/sw-cms/elements/product-description-reviews/config', () => {
    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPage',
            state() {
                return {
                    currentPage: {
                        type: 'landingpage',
                    },
                    currentMappingEntity: null,
                    currentDemoEntity: productMock,
                };
            },
        });
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPage').$reset();
    });

    it('should show product selector if page type is not product detail', async () => {
        const wrapper = await createWrapper();

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is product detail', async () => {
        const wrapper = await createWrapper();

        Shopware.Store.get('cmsPage').currentPage.type = 'product_detail';
        await flushPromises();

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });
});
