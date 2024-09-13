/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/extension-api/sw-extension-component-section', () => {
    let wrapper = null;
    let stubs;

    async function createWrapper(props = {}) {
        return mount(await wrapTestComponent('sw-extension-component-section', { sync: true }), {
            props: {
                positionIdentifier: 'test-position',
                ...props,
            },
            global: {
                stubs,
            },
        });
    }

    beforeAll(async () => {
        stubs = {
            'sw-card': await wrapTestComponent('sw-card'),
            'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
            'sw-tabs': await wrapTestComponent('sw-tabs'),
            'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
            'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
            'sw-ignore-class': true,
            'sw-iframe-renderer': {
                template: '<div></div>',
            },
            'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
            'sw-ai-copilot-badge': await wrapTestComponent('sw-ai-copilot-badge'),
            'sw-context-button': await wrapTestComponent('sw-context-button'),
            'sw-loader': await wrapTestComponent('sw-loader'),
            'sw-icon': await wrapTestComponent('sw-icon'),
            'router-link': true,
        };
    });

    beforeEach(async () => {
        Shopware.State.get('extensionComponentSections').identifier = {};
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not render tabs in card section', async () => {
        Shopware.State.commit('extensionComponentSections/addSection', {
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

    it('should render tabs in card section', async () => {
        Shopware.State.commit('extensionComponentSections/addSection', {
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

        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findAll('.sw-tabs-item');
        expect(tabs).toHaveLength(2);

        const activeTabs = wrapper.findAll('.sw-tabs-item--active');
        expect(activeTabs).toHaveLength(1);

        const activeTab = activeTabs.at(0);
        expect(activeTab.text()).toBe('Tab 1');
    });

    it('should switch tab when clicking', async () => {
        Shopware.State.commit('extensionComponentSections/addSection', {
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

    it.each(['dev', 'prod'])('should be deprecated in %s env', async (env) => {
        Shopware.State.commit('extensionComponentSections/addSection', {
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

        expect(mock).toHaveBeenCalledWith('CORE', 'The extension "TestExtension" uses a deprecated position identifier "test-position". Use position identifier XYZ instead.');

        if (restoreEnv) {
            process.env = restoreEnv;
        }
    });
});
