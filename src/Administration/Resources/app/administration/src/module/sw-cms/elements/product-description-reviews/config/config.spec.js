/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';

const productMock = {
    name: 'Awesome Product',
    description: 'This product is awesome',
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-product-description-reviews', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'mt-tabs': {
                        template: '<div class="mt-tabs-stub"></div>',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                required: false,
                                default: null,
                            },
                        },
                        emits: [
                            'new-item-active',
                            'extension-item-active',
                        ],
                    },
                    'sw-tabs': {
                        template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                    },
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-tabs-item': true,
                    'sw-entity-single-select': true,

                    'sw-product-variant-info': true,
                    'sw-select-result': true,
                    'sw-select-field': true,
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                },
                provide: {
                    cmsService: {
                        getCmsBlockRegistry: () => {
                            return {};
                        },
                        getCmsElementRegistry: () => {
                            return {
                                'product-description-reviews': {
                                    defaultConfig: {
                                        product: {
                                            source: 'static',
                                            value: null,
                                        },
                                        alignment: {
                                            source: 'static',
                                            value: null,
                                        },
                                    },
                                },
                            };
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
                    type: 'product-description-reviews',
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
        },
    );
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
        global.activeFeatureFlags = [];
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
        const alert = wrapper.find('[role="banner"]');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });

    it('should render meteor tabs when the feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const tabs = wrapper.getComponent('.mt-tabs-stub');

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-product-description-reviews');
        expect(tabs.props('defaultItem')).toBe('content');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-cms.elements.general.config.tab.content',
                name: 'content',
            },
            {
                label: 'sw-cms.elements.general.config.tab.options',
                name: 'options',
            },
        ]);

        await tabs.vm.$emit('new-item-active', 'options');

        expect(wrapper.vm.activeTab).toBe('options');
    });
});
