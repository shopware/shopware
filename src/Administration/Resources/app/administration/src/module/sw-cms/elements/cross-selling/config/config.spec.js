/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const productMock = {
    id: 'foo-bar',
    name: 'Small Silk Heart Worms',
};

async function createWrapper({ customCmsElementConfig = {}, featureActive = false } = {}) {
    return mount(
        await wrapTestComponent('sw-cms-el-config-cross-selling', {
            sync: true,
        }),
        {
            props: {
                element: {
                    config: {
                        title: {
                            value: '',
                        },
                        product: {
                            value: 'de8de156da134dabac24257f81ff282f',
                            source: 'static',
                        },
                        boxLayout: {
                            value: 'standard',
                        },
                        displayMode: {
                            value: 'standard',
                        },
                        elMinWidth: {
                            value: '300px',
                        },
                        speed: {
                            value: 300,
                        },
                        ...customCmsElementConfig,
                    },
                    data: {},
                },
                defaultConfig: {},
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-tabs': {
                        template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                    },
                    'sw-tabs-item': true,
                    'sw-container': true,
                    'sw-field': true,
                    'sw-text-field': true,
                    'sw-select-field': true,
                    'sw-select-result': true,
                    'sw-modal': true,
                    'sw-entity-single-select': true,
                    'sw-product-variant-info': true,
                    'mt-select': true,
                    'mt-text-field': true,
                    'mt-number-field': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
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
                    feature: {
                        isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                    },
                    cmsService: Shopware.Service('cmsService'),
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

describe('module/sw-cms/elements/cross-selling/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
        await import('src/module/sw-cms/elements/cross-selling');
    });

    beforeEach(() => {
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('should display a message if it is product page layout type', async () => {
        const wrapper = await createWrapper();

        const productSelect = wrapper.find('sw-entity-single-select-stub');

        expect(productSelect.exists()).toBe(true);
    });

    it('should display product select if it is product page layout type', async () => {
        Shopware.Store.get('cmsPage').setCurrentPage({
            type: 'product_detail',
        });
        const wrapper = await createWrapper();

        expect(wrapper.get('[role="banner"]').text()).toBe(
            'sw-cms.elements.crossSelling.config.infoText.productDetailElement',
        );
    });

    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-cms-element-cross-selling');
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
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('sw-entity-single-select-stub').exists()).toBe(true);
    });

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({ featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        await tabs.vm.$emit('new-item-active', 'options');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('options');
        expect(wrapper.find('sw-entity-single-select-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-cross-selling__tab-options-speed').exists()).toBe(true);
    });

    it('onProductChange clears the product if no id provided', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onProductChange(null);

        expect(wrapper.vm.element.config.product.value).toBeNull();
        expect(wrapper.vm.element.data.product).toBeNull();
    });

    it('onProductChange queries the product if id provided', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.onProductChange(productMock.id);

        expect(wrapper.vm.element.config.product.value).toBe(productMock.id);
        expect(wrapper.vm.element.data.product).toMatchObject(productMock);
    });
});
