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

        expect(wrapper.find('.sw-cms-el-config-product-description-reviews-config__tab-content').exists()).toBe(true);
        expect(wrapper.find('.sw-cms-el-config-text__tab-options').exists()).toBe(false);

        await wrapper.findComponent({ name: 'mt-tabs' }).vm.$emit('new-item-active', 'options');

        expect(wrapper.find('.sw-cms-el-config-product-description-reviews-config__tab-content').exists()).toBe(false);
        expect(wrapper.find('.sw-cms-el-config-text__tab-options').exists()).toBe(true);
    });


    it('renders registered extension tab content and ignores unknown tab ids when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        Shopware.Store.get('tabs').tabItems['sw-cms-element-product-description-reviews'] = [
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

        Shopware.Store.get('tabs').tabItems['sw-cms-element-product-description-reviews'] = [];
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
});
