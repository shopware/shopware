/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';

const productMock = {
    id: 'foo-bar',
    name: 'Small Silk Heart Worms',
};

async function createWrapper(customCmsElementConfig) {
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
                            source: 'static',
                        },
                        displayMode: {
                            value: 'standard',
                            source: 'static',
                        },
                        elMinWidth: {
                            value: '300px',
                            source: 'static',
                        },
                        speed: {
                            value: 300,
                            source: 'static',
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
                        name: 'sw-tabs',
                        template: '<div class="sw-tabs"><slot></slot><slot name="content" active="content"></slot></div>',
                    },
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: ['items', 'defaultItem', 'positionIdentifier', 'routeExtensionTabs'],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" :route-extension-tabs="routeExtensionTabs" @new-item-active="$emit(\'new-item-active\', $event)" />',
                    },
                    'sw-extension-component-section': {
                        name: 'sw-extension-component-section',
                        props: ['positionIdentifier'],
                        template: '<div class="sw-extension-component-section"></div>',
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
    });

    beforeEach(() => {
        global.activeFeatureFlags = [];
        Shopware.Store.get('cmsPage').resetCmsPageState();
    });

    it('renders legacy tabs when V6_8_0_0 is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs items when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(mtTabs.exists()).toBe(true);
        expect(mtTabs.props('items')).toEqual([
            { label: 'sw-cms.elements.general.config.tab.content', name: 'content' },
            { label: 'sw-cms.elements.general.config.tab.options', name: 'options' },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('content');
        expect(mtTabs.props('routeExtensionTabs')).toBe(false);
    });

    it('updates active tab from mt-tabs events', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'options' });
        expect(wrapper.vm.activeTab).toBe('options');

        await mtTabs.vm.$emit('new-item-active', 'content');
        expect(wrapper.vm.activeTab).toBe('content');
    });

    it('renders active mt-tabs panes when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-el-config-cross-selling__tab-content').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-el-config-cross-selling__tab-options').exists()).toBe(false);

        await wrapper.findComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'options');

        expect(wrapper.find('.sw-cms-el-config-cross-selling__tab-content').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-cross-selling__tab-options').exists()).toBe(true);
    });


    it('renders registered extension tab content and ignores unknown tab ids when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-element-cross-selling'] = [
            { componentSectionId: 'extension-tab' },
        ];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        await mtTabs.vm.$emit('new-item-active', { name: 'extension-tab' });

        expect(wrapper.vm.activeTabIsExtensionTab).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );

        await mtTabs.vm.$emit('new-item-active', 'unknown-tab');

        expect(wrapper.vm.activeTab).toBe('extension-tab');
        expect(wrapper.vm.activeTabIsExtensionTab).toBe(true);
        expect(wrapper.findComponent({ name: 'sw-extension-component-section' }).props('positionIdentifier')).toBe(
            'extension-tab',
        );

        Shopware.Store.get('tabs').tabItems['sw-cms-element-cross-selling'] = [];
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
