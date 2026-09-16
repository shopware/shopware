/**
 * @sw-package checkout
 */

import {
    createWrapper,
    routes,
    selectMtSelectOptionByText,
    setupListingHooks,
    shopwareService,
} from './sw-extension-my-extensions-listing.fixtures';

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    setupListingHooks();

    it('runtime management disabled should be there', async () => {
        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = true;
        const wrapper = await createWrapper();

        const runtimeManagement = wrapper.find('.sw-extension-my-extensions-listing__runtime-extension-warning');
        expect(runtimeManagement.exists()).toBe(true);
    });

    it('should show the empty state with a store button when no extensions are installed', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([]);
        const wrapper = await createWrapper();

        const emptyState = wrapper.find('.sw-extension-my-extensions-listing__empty-state');
        expect(emptyState.classes()).toContain('mt-empty-state');

        wrapper.vm.$router.push = jest.fn();
        await emptyState.find('.mt-button').trigger('click');

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.extension.store.listing',
        });
    });

    it('should show the empty state without a store button when the active filter matches no extensions', async () => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            {
                name: 'Test',
                installedAt: 'foo',
                active: false,
                updatedAt: null,
            },
        ]);
        const wrapper = await createWrapper();

        const switchField = wrapper.find('.mt-switch input[type="checkbox"]');
        await switchField.trigger('click');

        const emptyState = wrapper.find('.sw-extension-my-extensions-listing__empty-state');
        expect(emptyState.classes()).toContain('mt-empty-state');
        expect(emptyState.find('.mt-button').exists()).toBe(false);
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

        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            {
                name: 'Test',
                installedAt: 'some date',
                isTheme: true,
            },
        ]);

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
                sorting: 'updated-at',
            },
        });
    });

    it('should persist the selected sorting option in the route', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(
            wrapper,
            'sw-extension.my-extensions.listing.controls.filterOptions.name-asc',
            '.mt-select__selection',
        );

        expect(wrapper.vm.$route.query.sorting).toBe('name-asc');
    });

    it('should apply the sorting option from the route after loading', async () => {
        const wrapper = await createWrapper({ query: { sorting: 'name-asc' } });
        const extensions = [
            'Zeta',
            'Alpha',
        ].map((name) => ({
            name,
            label: name,
            updatedAt: null,
        }));

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('.sw-self-maintained-extension-card').map((card) => card.text())).toEqual([
            'Alpha',
            'Zeta',
        ]);
    });

    it('should update the route with the new values from pagination', async () => {
        const wrapper = await createWrapper();

        // load 40 extensions
        const extensions = Array(40)
            .fill()
            .map((_, i) => {
                return {
                    name: `extension card number ${i}`,
                    installedAt: `foo-${i}`,
                    updatedAt: null,
                };
            });

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await wrapper.vm.$nextTick();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(25);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 0');

        // go to second page
        const nextButton = wrapper.find('.sw-pagination__page-button-next');
        await nextButton.trigger('click');

        // simulate change in url
        await wrapper.vm.$router.push({
            name: wrapper.vm.$route.name,
            query: { page: 2 },
        });

        // check if it shows now only 15 extensions
        extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(15);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 25');
    });

    it('should search the extensions', async () => {
        const wrapper = await createWrapper();

        // load 40 extensions
        const extensions = Array(40)
            .fill()
            .map((_, i) => {
                return {
                    name: `extension card number ${i}`,
                    installedAt: `foo-${i}`,
                    updatedAt: null,
                };
            });

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await wrapper.vm.$nextTick();

        // check if only shows first 25 extensions
        let extensionCards = wrapper.findAllComponents('.sw-self-maintained-extension-card');
        expect(extensionCards).toHaveLength(25);
        expect(extensionCards.at(0).props('extension').name).toBe('extension card number 0');

        // enter search value
        await wrapper.vm.$router.push({
            name: wrapper.vm.$route.name,
            query: { term: 'number 1' },
        });

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

        const activeExtensions = Array(20)
            .fill()
            .map((_, i) => {
                return {
                    name: `extension card number ${i}`,
                    installedAt: `foo-${i}`,
                    active: true,
                    updatedAt: null,
                };
            });

        const inactiveExtensions = Array(5)
            .fill()
            .map((_, i) => {
                const index = i + activeExtensions.length;

                return {
                    name: `extension card number ${index}`,
                    installedAt: `foo-${index}`,
                    active: false,
                    updatedAt: null,
                };
            });

        Shopware.Store.get('shopwareExtensions').setMyExtensions([
            ...activeExtensions,
            ...inactiveExtensions,
        ]);

        await wrapper.vm.$nextTick();

        const allExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(allExtensions).toHaveLength(25);

        const switchField = wrapper.find('.mt-switch input[type="checkbox"]');
        await switchField.trigger('click');

        const filteredExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        expect(filteredExtensions).toHaveLength(20);
    });

    it('should sort the extensions by their name in an ascending order', async () => {
        const wrapper = await createWrapper();

        const extensionNames = [
            'very smart plugin',
            '#1 best plugin',
            'semi good plugin',
        ];
        const extensions = extensionNames.map((name, i) => {
            return {
                name,
                label: name,
                installedAt: `foo-${i}`,
                active: true,
                updatedAt: null,
            };
        });

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await wrapper.vm.$nextTick();

        await selectMtSelectOptionByText(
            wrapper,
            'sw-extension.my-extensions.listing.controls.filterOptions.name-desc',
            '.mt-select__selection',
        );

        const correctOrder = [
            'very smart plugin',
            'semi good plugin',
            '#1 best plugin',
        ];
        const orderedExtensions = wrapper.findAll('.sw-self-maintained-extension-card');
        orderedExtensions.forEach((currentWrapper, i) => {
            const currentWrapperLabel = currentWrapper.text();

            expect(currentWrapperLabel).toBe(correctOrder[i]);
        });
    });

    it('should sort the extensions by their name in an decending order', async () => {
        const wrapper = await createWrapper();

        const extensionNames = [
            'very smart plugin',
            '#1 best plugin',
            'semi good plugin',
        ];
        const extensions = extensionNames.map((name, i) => {
            return {
                name,
                label: name,
                installedAt: `foo-${i}`,
                active: true,
                updatedAt: null,
            };
        });

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await selectMtSelectOptionByText(
            wrapper,
            'sw-extension.my-extensions.listing.controls.filterOptions.name-asc',
            '.mt-select__selection',
        );

        const correctOrder = [
            '#1 best plugin',
            'semi good plugin',
            'very smart plugin',
        ];
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

        Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);

        await wrapper.vm.$nextTick();

        // not setting the sorting option via the dropdown because the default sorting is by their updatedAt value

        const correctOrder = [
            'extension no. 2',
            'extension no. 0',
            'extension no. 1',
        ];
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

        Shopware.Store.get('context').app.config.settings.appUrlReachable = false;

        await wrapper.vm.$nextTick();

        const alert = wrapper.find('.sw-extension-my-extensions-listing__app-url-warning');
        expect(alert.isVisible()).toBe(true);
    });
});
