/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/extension-api/sw-extension-component-section', () => {
    let wrapper = null;
    let stubs;

    function addSectionWithTabs() {
        Shopware.Store.get('extensionComponentSections').addSection({
            component: 'card',
            positionId: 'test-position',
            props: {
                title: 'test-card',
                subtitle: 'test-card-description',
                tabs: [
                    {
                        name: 'tab-1',
                        label: 'Tab 1',
                        locationId: 'tab-1',
                    },
                    {
                        name: 'tab-2',
                        label: 'Tab 2',
                        locationId: 'tab-2',
                    },
                ],
            },
        });
    }

    async function createWrapper(props = {}) {
        return mount(
            await wrapTestComponent('sw-extension-component-section', {
                sync: true,
            }),
            {
                props: {
                    positionIdentifier: 'test-position',
                    ...props,
                },
                global: {
                    provide: {
                        feature: {
                            isActive: (flag) => global.activeFeatureFlags.includes(flag),
                        },
                    },
                    stubs,
                },
            },
        );
    }

    beforeAll(async () => {
        stubs = {
            'sw-tabs': await wrapTestComponent('sw-tabs'),
            'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
            'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
            'mt-tabs': {
                name: 'mt-tabs',
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
            'sw-ignore-class': true,
            'sw-iframe-renderer': {
                template: '<div></div>',
            },
            'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
            'sw-ai-copilot-badge': await wrapTestComponent('sw-ai-copilot-badge'),
            'sw-context-button': await wrapTestComponent('sw-context-button'),
            'sw-loader': await wrapTestComponent('sw-loader'),
            'router-link': true,
        };
    });

    beforeEach(async () => {
        global.activeFeatureFlags = [];
        Shopware.Store.get('extensionComponentSections').identifier = {};
    });

    it('should not render tabs in card section', async () => {
        Shopware.Store.get('extensionComponentSections').addSection({
            component: 'card',
            positionId: 'test-position',
            props: {
                title: 'test-card',
                subtitle: 'test-card-description',
            },
        });

        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.find('.sw-tabs');
        expect(tabs.exists()).toBe(false);
    });

    it('should render deprecated tabs in card section when the major feature flag is inactive', async () => {
        addSectionWithTabs();

        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findAll('.sw-tabs-item');
        expect(tabs).toHaveLength(2);

        const activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        const activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('Tab 1');
    });

    it('should render meteor tabs in card section when the major feature flag is active', async () => {
        global.activeFeatureFlags = ['v6.8.0.0'];
        addSectionWithTabs();

        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });
        expect(tabs.exists()).toBe(true);
        expect(tabs.props('positionIdentifier')).toBe('');
        expect(tabs.props('defaultItem')).toBe('tab-1');
        expect(tabs.props('items')).toEqual([
            {
                label: 'Tab 1',
                name: 'tab-1',
            },
            {
                label: 'Tab 2',
                name: 'tab-2',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it('should switch tab when clicking deprecated tabs', async () => {
        addSectionWithTabs();

        wrapper = await createWrapper();
        await flushPromises();

        // Default active tab
        const defaultIframe = wrapper.findComponent(stubs['sw-iframe-renderer']);
        expect(defaultIframe.vm.$attrs['location-id']).toBe('tab-1');

        // Click the 2nd tab
        const tabItems = wrapper.findAll('.sw-tabs-item');
        await tabItems.at(1).trigger('click');

        // Check tab content
        const activeIframe = wrapper.findComponent(stubs['sw-iframe-renderer']);
        expect(activeIframe.vm.$attrs['location-id']).toBe('tab-2');
    });

    it('should switch tab when meteor tabs emit a new active item', async () => {
        global.activeFeatureFlags = ['v6.8.0.0'];
        addSectionWithTabs();

        wrapper = await createWrapper();
        await flushPromises();

        // Default active tab
        const defaultIframe = wrapper.findComponent(stubs['sw-iframe-renderer']);
        expect(defaultIframe.vm.$attrs['location-id']).toBe('tab-1');

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });
        await tabs.vm.$emit('new-item-active', 'tab-2');
        await flushPromises();

        // Check tab content
        const activeIframe = wrapper.findComponent(stubs['sw-iframe-renderer']);
        expect(activeIframe.vm.$attrs['location-id']).toBe('tab-2');
        expect(tabs.props('defaultItem')).toBe('tab-2');
    });

    it.each([
        'dev',
        'prod',
    ])('should be deprecated in %s env', async (env) => {
        Shopware.Store.get('extensionComponentSections').addSection({
            component: 'card',
            positionId: 'test-position',
            props: {
                title: 'test-card',
                subtitle: 'test-card-description',
            },
            extensionName: 'TestExtension',
        });

        let restoreEnv;
        const mock = jest.fn();
        if (env === 'prod') {
            // In prod the deprecation will be thrown via warn
            Shopware.Utils.debug.warn = mock;

            // Save previous env to restore later and set env to prod
            restoreEnv = process.env;
            process.env = 'prod';
        } else {
            // In dev the deprecation will be thrown via warn
            Shopware.Utils.debug.error = mock;
        }

        wrapper = await createWrapper({
            deprecated: true, // deprecate position
            deprecationMessage: 'Use position identifier XYZ instead.', // test additional info as well
        });
        await flushPromises();

        expect(mock).toHaveBeenCalledWith(
            'CORE',
            'The extension "TestExtension" uses a deprecated position identifier "test-position". Use position identifier XYZ instead.',
        );

        if (restoreEnv) {
            process.env = restoreEnv;
        }
    });
});
