import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';

const routes = [{
    name: 'sw.extension.my-extensions.listing.app',
    path: '/sw/extension/my-extensions/listing/app',
    query: {},
}, {
    name: 'sw.extension.my-extensions.listing.theme',
    path: '/sw/extension/my-extensions/listing/theme',
    query: {},
}];

const shopwareService = new ShopwareService({}, {}, {}, {});
shopwareService.updateExtensionData = jest.fn();

async function createWrapper() {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    await router.push(routes[0]);
    await router.isReady();

    return mount(await wrapTestComponent('sw-extension-my-extensions-listing', { sync: true }), {
        global: {
            plugins: [router],
            stubs: {
                'router-link': true,
                'sw-self-maintained-extension-card': {
                    template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
                    props: ['extension'],
                },
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-meteor-card': true,
                'sw-pagination': await wrapTestComponent('sw-pagination', { sync: true }),
                'sw-icon': true,
                'sw-field': true,
                // eslint-disable-next-line max-len
                'sw-extension-my-extensions-listing-controls': await wrapTestComponent('sw-extension-my-extensions-listing-controls', { sync: true }),
                'sw-switch-field': await wrapTestComponent('sw-switch-field', { sync: true }),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-alert': await wrapTestComponent('sw-alert', { sync: true }),
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {};
                    },
                },
                shopwareExtensionService: shopwareService,
            },
        },
        attachTo: document.body,
    });
}

/**
 * @package checkout
 */
describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    beforeAll(() => {
        if (Shopware.State.get('shopwareExtensions')) {
            Shopware.State.unregisterModule('shopwareExtensions');
        }

        Shopware.State.registerModule('shopwareExtensions', {
            namespaced: true,
            state: {
                myExtensions: {
                    data: [
                        {
                            name: 'Test',
                            installedAt: null,
                        },
                    ],
                },
            },
            mutations: {
                setExtensions(state, extensions) {
                    state.myExtensions.data = extensions;
                },
            },
        });

        if (Shopware.State.get('context')) {
            Shopware.State.unregisterModule('context');
        }

        Shopware.State.registerModule('context', {
            namespaced: true,
            state: {
                app: {
                    config: {
                        settings: {
                            appUrlReachable: true,
                        },
                    },
                },
                api: {
                    assetsPath: '/',
                },
            },
        });
    });

    beforeEach(async () => {
        Shopware.State.commit('shopwareExtensions/setExtensions', [
            {
                name: 'Test',
                installedAt: null,
            },
        ]);
    });

    it('openStore should call router', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$router = {
            push: jest.fn(),
        };

        wrapper.vm.openStore();

        expect(wrapper.vm.$router.push).toHaveBeenCalled();
    });

    it('openThemesStore should call router', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.$router = {
            push: jest.fn(),
        };

        wrapper.vm.openThemesStore();

        expect(wrapper.vm.$router.push).toHaveBeenCalled();
    });

    it('updateList should call update extensions', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.updateList();

        expect(shopwareService.updateExtensionData).toHaveBeenCalled();
    });

    it('extensionList default has a app', async () => {
        const wrapper = await createWrapper();

        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards).toHaveLength(1);
    });

    it('extensionList default has a no themes', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$router.push(routes[1]);

        await wrapper.vm.$nextTick();

        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards).toHaveLength(0);
    });

    it('extensionList withThemes has a theme', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$router.push(routes[1]);

        Shopware.State.commit('shopwareExtensions/setExtensions', [{
            name: 'Test',
            installedAt: 'some date',
            isTheme: true,
        }]);

        await wrapper.vm.$nextTick();

        const extensionCards = wrapper.findAll('.sw-self-maintained-extension-card');

        expect(extensionCards).toHaveLength(1);
    });

    it('should update the route with the default values', async () => {
        const wrapper = await createWrapper();

        await flushPromises();

        expect(wrapper.vm.$route).toMatchObject({
            name: 'sw.extension.my-extensions.listing.app',
            params: {},
            query: {
                limit: '25',
                page: '1',
            },
        });
    });

    it('should update the route with the new values from pagination', async () => {
        const wrapper = await createWrapper();

        // load 40 extensions
        const extensions = Array(40).fill().map((_, i) => {
            return { name: `extension card number ${i}`, installedAt: `foo-${i}`, updatedAt: null };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(25);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 0');

        // go to second page
        const nextButton = wrapper.find('.sw-pagination__page-button-next');
        await nextButton.trigger('click');

        // simulate change in url
        await wrapper.vm.$router.push({ name: wrapper.vm.$route.name, query: { page: 2 } });

        // check if it shows now only 15 extensions
        extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(15);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 25');
    });

    it('should search the extensions', async () => {
        const wrapper = await createWrapper();

        // load 60 extensions
        const extensions = Array(40).fill().map((_, i) => {
            return { name: `extension card number ${i}`, installedAt: `foo-${i}`, updatedAt: null };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(25);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 0');

        // enter search value
        await wrapper.vm.$router.push({ name: wrapper.vm.$route.name, query: { term: 'number 1' } });

        // check if it shows now only 11 extensions
        extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(11);

        // check some random entries
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 1');
        expect(extensionCards.at(1).props('extension').name).toBe('extension card number 10');
        expect(extensionCards.at(10).props('extension').name).toBe('extension card number 19');
    });

    it('should filter the extensions by their active state', async () => {
        const wrapper = await createWrapper();

        const activeExtensions = Array(20).fill().map((_, i) => {
            return { name: `extension card number ${i}`, installedAt: `foo-${i}`, active: true, updatedAt: null };
        });

        const inactiveExtensions = Array(5).fill().map((_, i) => {
            const index = i + activeExtensions.length;

            return {
                name: `extension card number ${index}`,
                installedAt: `foo-${index}`,
                active: false,
                updatedAt: null,
            };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', [...activeExtensions, ...inactiveExtensions]);

        await wrapper.vm.$nextTick();

        const allExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(allExtensions).toHaveLength(25);

        const switchField = wrapper.find('.sw-field--switch input[type="checkbox"]');
        await switchField.trigger('click');

        const filteredExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(filteredExtensions).toHaveLength(20);
    });

    it('should sort the extensions by their name in an ascending order', async () => {
        const wrapper = await createWrapper();

        const extensionNames = ['very smart plugin', '#1 best plugin', 'semi good plugin'];
        const extensions = extensionNames.map((name, i) => {
            return { name, label: name, installedAt: `foo-${i}`, active: true, updatedAt: null };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        const sortingOption = wrapper.find('option[value="name-desc"]');

        // setting sorting option
        await sortingOption.setSelected();

        const correctOrder = ['very smart plugin', 'semi good plugin', '#1 best plugin'];
        const orderedExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        orderedExtensions.forEach((currentWrapper, i) => {
            const currentWrapperLabel = currentWrapper.text();

            expect(currentWrapperLabel).toBe(correctOrder[i]);
        });
    });

    it('should sort the extensions by their name in an decending order', async () => {
        const wrapper = await createWrapper();

        const extensionNames = ['very smart plugin', '#1 best plugin', 'semi good plugin'];
        const extensions = extensionNames.map((name, i) => {
            return { name, label: name, installedAt: `foo-${i}`, active: true, updatedAt: null };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        const sortingOption = wrapper.find('option[value="name-asc"]');

        // setting sorting option
        await sortingOption.setSelected();

        const correctOrder = ['#1 best plugin', 'semi good plugin', 'very smart plugin'];
        const orderedExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        orderedExtensions.forEach((currentWrapper, i) => {
            const currentWrapperLabel = currentWrapper.text();

            expect(currentWrapperLabel).toBe(correctOrder[i]);
        });
    });

    it('should sort the extensions by their updatedAt property', async () => {
        const wrapper = await createWrapper();

        const unsortedUpdatedAtValues = [
            '2021-04-22T23:00:00.000Z',
            '2021-01-22T23:00:00.000Z',
            '2021-05-22T23:00:00.000Z',
        ];
        const extensions = unsortedUpdatedAtValues.map((updatedAtValue, i) => {
            const extensionName = `extension no. ${i}`;

            return {
                name: extensionName,
                label: extensionName,
                installedAt: `foo-${i}`,
                updatedAt: { date: updatedAtValue },
                active: true,
            };
        });

        Shopware.State.commit('shopwareExtensions/setExtensions', extensions);

        await wrapper.vm.$nextTick();

        // not setting the sorting option via the dropdown because the default sorting is by their updatedAt value

        const correctOrder = ['extension no. 2', 'extension no. 0', 'extension no. 1'];
        const orderedExtensions = wrapper.findAll('.sw-self-maintained-extension-card');

        orderedExtensions.forEach((currentWrapper, i) => {
            const currentWrapperLabel = currentWrapper.text();

            expect(currentWrapperLabel).toBe(correctOrder[i]);
        });
    });

    it('should not show a warning if the APP_URL is setup correctly', async () => {
        const wrapper = await createWrapper();

        const alert = wrapper.find('.sw-extension-my-extensions-listing__app-url-warning');
        expect(alert.exists()).toBe(false);
    });

    it('should show a warning if the APP_URL is not setup correctly', async () => {
        const wrapper = await createWrapper();

        Shopware.State.get('context').app.config.settings.appUrlReachable = false;

        await wrapper.vm.$nextTick();

        const alert = wrapper.find('.sw-extension-my-extensions-listing__app-url-warning');
        expect(alert.isVisible()).toBe(true);
    });
});
