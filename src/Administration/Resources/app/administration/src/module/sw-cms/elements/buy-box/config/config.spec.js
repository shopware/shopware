/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const productMock = {
    name: 'Lorem Ipsum dolor',
    id: '1234',
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
    return mount(
        await wrapTestComponent('sw-cms-el-config-buy-box', {
            sync: true,
        }),
        {
            props: {
                element: {
                    type: 'buy-box',
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
                        getCmsElementRegistry: () => ({
                            'buy-box': {
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
                        }),
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
        },
    );
}

describe('module/sw-cms/elements/buy-box/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/buy-box');
    });

    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    afterEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should show product selector if page type is not product detail', async () => {
        const wrapper = await createWrapper();
        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('sw-alert-stub');

        expect(productSelector.exists()).toBeTruthy();
        expect(alert.exists()).toBeFalsy();
    });

    it('should show alert information if page type is product detail', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_detail',
        });
        const wrapper = await createWrapper();

        const productSelector = wrapper.find('sw-entity-single-select-stub');
        const alert = wrapper.find('[role="banner"]');

        expect(productSelector.exists()).toBeFalsy();
        expect(alert.exists()).toBeTruthy();
    });

    it('should fetch products via API if product is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onProductChange(productMock.id);

        expect(wrapper.vm.element.config.product.value).toBe(productMock.id);
        expect(wrapper.vm.element.data.productId).toBe(productMock.id);
        expect(wrapper.vm.element.data.product).toMatchObject(productMock);
    });

    it('should delete product if no product is selected', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.onProductChange(null);

        expect(wrapper.vm.element.config.product.value).toBeNull();
        expect(wrapper.vm.element.data.productId).toBeNull();
        expect(wrapper.vm.element.data.product).toBeNull();
    });

    it('should render meteor tabs when the feature flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const tabs = wrapper.getComponent('.mt-tabs-stub');

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-config-buy-box');
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
