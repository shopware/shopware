/**
 * @package admin
 */

import { mount } from '@vue/test-utils_v3';
import Vue from 'vue';

describe('src/app/component/extension-api/sw-extension-component-section', () => {
    let wrapper = null;
    let stubs;

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-extension-component-section', { sync: true }), {
            props: {
                positionIdentifier: 'test-position',
            },
            global: {
                stubs,
            },
        });
    }

    beforeAll(async () => {
        stubs = {
            'sw-card': await wrapTestComponent('sw-card'),
            'sw-tabs': await wrapTestComponent('sw-tabs'),
            'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
            'sw-ignore-class': true,
            'sw-iframe-renderer': {
                template: '<div></div>',
            },
        };
    });

    beforeEach(async () => {
        Vue.set(Shopware.State.get('extensionComponentSections'), 'identifier', {});
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
});
