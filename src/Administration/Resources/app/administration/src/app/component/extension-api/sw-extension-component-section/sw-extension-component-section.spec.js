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
        Shopware.Store.get('extensionComponentSections').identifier = {};
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should not render tabs in card section', async () => {
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

        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should not render tabs in card section', async () => {
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

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should render deprecated tabs in card section', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs in card section', async () => {
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

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy sw-tabs branch.
    it.deprecated('v6.8.0.0')('should switch tab when clicking deprecated tabs', async () => {
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

    it.activeFeatureFlags(['v6.8.0.0'])('should switch tab when meteor tabs emit a new active item', async () => {
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

    describe('section ordering', () => {
        function registerExtension(name, sourceType) {
            Shopware.Store.get('extensions').addExtension({
                name,
                baseUrl: `https://example.com/${name}`,
                permissions: {},
                type: 'app',
                sourceType,
                active: true,
            });
        }

        function addSection(extensionName, priority) {
            Shopware.Store.get('extensionComponentSections').addSection({
                component: 'card',
                positionId: 'test-position',
                props: { title: extensionName, locationId: extensionName },
                extensionName,
                priority,
            });
        }

        const orderedNames = () => wrapper.vm.componentSections.map((section) => section.extensionName);

        beforeEach(() => {
            Shopware.Store.get('extensions').extensionsState = {};
        });

        it('renders service sections above app sections regardless of registration order', async () => {
            registerExtension('AppExtension', 'local');
            registerExtension('ServiceExtension', 'service');

            // App registers first (wins the race today), service second.
            addSection('AppExtension');
            addSection('ServiceExtension');

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'ServiceExtension',
                'AppExtension',
            ]);
        });

        it('orders by ascending priority within the same group', async () => {
            registerExtension('AppA', 'local');
            registerExtension('AppB', 'local');
            registerExtension('AppC', 'local');

            addSection('AppB', 20);
            addSection('AppC', 30);
            addSection('AppA', 10);

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'AppA',
                'AppB',
                'AppC',
            ]);
        });

        it('renders entries without a priority below those that set one', async () => {
            registerExtension('AppUnset', 'local');
            registerExtension('AppPositioned', 'local');

            addSection('AppUnset');
            addSection('AppPositioned', 999);

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'AppPositioned',
                'AppUnset',
            ]);
        });

        it('keeps registration order for entries with an unset priority (no name bias)', async () => {
            registerExtension('Charlie', 'local');
            registerExtension('Alpha', 'local');
            registerExtension('Bravo', 'local');

            // No priority on any → all unset → ties keep their registration order, not alphabetical.
            addSection('Charlie');
            addSection('Alpha');
            addSection('Bravo');

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'Charlie',
                'Alpha',
                'Bravo',
            ]);
        });

        it('keeps registration order for entries sharing the same priority', async () => {
            registerExtension('First', 'local');
            registerExtension('Second', 'local');

            addSection('Second', 10);
            addSection('First', 10);

            wrapper = await createWrapper();
            await flushPromises();

            // Equal priority → stable sort preserves insertion order (Second was registered first).
            expect(orderedNames()).toEqual([
                'Second',
                'First',
            ]);
        });

        it('keeps services on top even when an app has a lower priority', async () => {
            registerExtension('ServiceExtension', 'service');
            registerExtension('AppExtension', 'local');

            addSection('ServiceExtension', 100);
            addSection('AppExtension', 1);

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'ServiceExtension',
                'AppExtension',
            ]);
        });

        it('treats sections whose extension is unknown as non-services', async () => {
            registerExtension('ServiceExtension', 'service');

            addSection('ServiceExtension');
            addSection('UnknownExtension');

            wrapper = await createWrapper();
            await flushPromises();

            expect(orderedNames()).toEqual([
                'ServiceExtension',
                'UnknownExtension',
            ]);
        });

        it('orders distinct priorities deterministically regardless of registration order', async () => {
            registerExtension('AppA', 'local');
            registerExtension('AppB', 'local');
            registerExtension('ServiceZ', 'service');

            addSection('AppB', 20);
            addSection('ServiceZ', 50);
            addSection('AppA', 5);

            wrapper = await createWrapper();
            await flushPromises();

            const firstRun = orderedNames();

            // Re-register in a different order to simulate a page refresh.
            Shopware.Store.get('extensionComponentSections').identifier = {};
            addSection('AppA', 5);
            addSection('ServiceZ', 50);
            addSection('AppB', 20);
            await flushPromises();

            // Service first, then apps by ascending priority — identical both runs because
            // every entry has a distinct priority (no reliance on registration order).
            expect(firstRun).toEqual([
                'ServiceZ',
                'AppA',
                'AppB',
            ]);
            expect(orderedNames()).toEqual(firstRun);
        });
    });
});
