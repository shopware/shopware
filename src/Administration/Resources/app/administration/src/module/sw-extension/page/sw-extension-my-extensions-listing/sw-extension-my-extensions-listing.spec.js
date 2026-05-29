import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';
import selectMtSelectOptionByText from '../../../../../test/_helper_/select-mt-select-by-text';

const routes = [
    {
        name: 'sw.extension.my-extensions.listing.app',
        path: '/sw/extension/my-extensions/listing/app',
        query: {},
        component: {},
    },
    {
        name: 'sw.extension.my-extensions.listing.theme',
        path: '/sw/extension/my-extensions/listing/theme',
        query: {},
        component: {},
    },
];

const shopwareService = new ShopwareService({}, {}, {}, {});
shopwareService.updateExtensionData = jest.fn();

function cardActionFns() {
    return {
        installExtension: jest.fn(() => Promise.resolve()),
        activateExtension: jest.fn(() => Promise.resolve()),
        deactivateExtension: jest.fn(() => Promise.resolve()),
        updateExtension: jest.fn(() => Promise.resolve()),
        closeModalAndUninstallExtension: jest.fn(() => Promise.resolve()),
    };
}

function setMyExtensions(extensions) {
    Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);
}

function makeCardStub({ methods = {}, emits = [], deferredClass = false } = {}) {
    const template = deferredClass
        ? '<div class="sw-self-maintained-extension-card" :class="{ \'is--deferred\': deferReload }">{{ extension.label }}</div>'
        : '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>';

    return {
        template,
        props: [
            'extension',
            'selected',
            'deferReload',
        ],
        emits,
        methods,
    };
}

async function createWrapper({ aclCan = () => true, cardStub } = {}) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    await router.push(routes[0]);
    await router.isReady();

    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing', {
            sync: true,
        }),
        {
            global: {
                plugins: [router],
                stubs: {
                    'router-link': true,
                    'sw-self-maintained-extension-card': cardStub ?? {
                        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
                        props: ['extension'],
                    },
                    'sw-meteor-card': true,
                    'sw-extension-bulk-actions-bar': await wrapTestComponent('sw-extension-bulk-actions-bar', {
                        sync: true,
                    }),
                    'sw-pagination': await wrapTestComponent('sw-pagination', {
                        sync: true,
                    }),
                    'sw-field': true,
                    'sw-extension-my-extensions-listing-controls': await wrapTestComponent(
                        'sw-extension-my-extensions-listing-controls',
                        { sync: true },
                    ),

                    'sw-base-field': await wrapTestComponent('sw-base-field', {
                        sync: true,
                    }),
                    'sw-field-error': await wrapTestComponent('sw-field-error', { sync: true }),
                    'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                    'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-skeleton': true,
                    'sw-external-link': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-loader': true,
                    'sw-extension-component-section': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {};
                        },
                    },
                    shopwareExtensionService: shopwareService,
                    cacheApiService: {
                        clear: jest.fn(() => Promise.resolve()),
                    },
                    acl: {
                        can: aclCan,
                    },
                },
            },
            attachTo: document.body,
        },
    );
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    beforeAll(() => {
        Shopware.Store.get('shopwareExtensions').setMyExtensions([{ name: 'Test', installedAt: null }]);

        if (Shopware.Store.get('context')) {
            Shopware.Store.unregister('context');
        }

        Shopware.Store.register({
            id: 'context',
            state: () => ({
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
            }),
        });
    });

    beforeEach(async () => {
        setMyExtensions([
            {
                name: 'Test',
                installedAt: null,
            },
        ]);

        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = false;
        Shopware.Store.get('context').app.config.settings.appUrlReachable = true;

        shopwareService.updateExtensionData.mockClear();
    });

    it('runtime management disabled should be there', async () => {
        Shopware.Store.get('context').app.config.settings.disableExtensionManagement = true;
        const wrapper = await createWrapper();

        const runtimeManagement = wrapper.find('.sw-extension-my-extensions-listing__runtime-extension-warning');
        expect(runtimeManagement.exists()).toBe(true);
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
            },
        });
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

    describe('bulk operations: selection model', () => {
        it('should report no selection initially', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();

            expect(wrapper.vm.hasSelection).toBe(false);
            expect(wrapper.vm.selectedExtensions).toEqual([]);
        });

        it('should add a name immutably and dedupe on select change with checked true', async () => {
            const wrapper = await createWrapper();

            const before = wrapper.vm.selectedNames;
            wrapper.vm.onSelectChange({ name: 'A' }, true);

            expect(wrapper.vm.selectedNames).toEqual(['A']);
            expect(wrapper.vm.selectedNames).not.toBe(before);

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            expect(wrapper.vm.selectedNames).toEqual(['A']);
        });

        it('should remove a name on select change with checked false', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            wrapper.vm.onSelectChange({ name: 'A' }, false);

            expect(wrapper.vm.selectedNames).toEqual(['B']);
        });

        it('should expose selectedExtensions filtered from the store by selected names', async () => {
            setMyExtensions([
                { name: 'A', installedAt: null, updatedAt: null },
                { name: 'B', installedAt: null, updatedAt: null },
                { name: 'C', installedAt: null, updatedAt: null },
            ]);
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'C' }, true);

            expect(wrapper.vm.selectedExtensions.map((extension) => extension.name)).toEqual([
                'A',
                'C',
            ]);
            expect(wrapper.vm.hasSelection).toBe(true);
        });

        it('should report isSelected per extension', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);

            expect(wrapper.vm.isSelected({ name: 'A' })).toBe(true);
            expect(wrapper.vm.isSelected({ name: 'B' })).toBe(false);
        });

        it('should select only the visible page on selectAllVisible', async () => {
            const extensions = Array(40)
                .fill()
                .map((_, i) => ({
                    name: `extension-${i}`,
                    label: `extension-${i}`,
                    installedAt: `foo-${i}`,
                    updatedAt: null,
                }));
            setMyExtensions(extensions);

            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.selectAllVisible();

            // limit is 25, so only the first page is selected (per-page selection, not all 40)
            expect(wrapper.vm.selectedNames).toHaveLength(25);
            expect(wrapper.vm.selectedNames).toEqual(wrapper.vm.extensionListPaginated.map((extension) => extension.name));
        });

        it('should clear the selection on clearSelection', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            wrapper.vm.clearSelection();

            expect(wrapper.vm.selectedNames).toEqual([]);
        });
    });

    describe('bulk operations: actionApplies branch matrix', () => {
        it.each([
            [
                'install',
                { installedAt: null },
                true,
            ],
            [
                'install',
                { installedAt: 'x' },
                false,
            ],
            [
                'activate',
                { installedAt: 'x', active: false },
                true,
            ],
            [
                'activate',
                { installedAt: 'x', active: true },
                false,
            ],
            [
                'activate',
                { installedAt: null, active: false },
                false,
            ],
            [
                'deactivate',
                { installedAt: 'x', active: true, allowDisable: true },
                true,
            ],
            [
                'deactivate',
                { installedAt: 'x', active: true, allowDisable: false },
                false,
            ],
            [
                'deactivate',
                { installedAt: 'x', active: false, allowDisable: true },
                false,
            ],
            [
                'update',
                { installedAt: 'x', allowUpdate: true, latestVersion: '2.0', version: '1.0' },
                true,
            ],
            [
                'update',
                { installedAt: 'x', allowUpdate: true, latestVersion: '1.0', version: '1.0' },
                false,
            ],
            [
                'update',
                { installedAt: 'x', allowUpdate: false, latestVersion: '2.0', version: '1.0' },
                false,
            ],
            [
                'update',
                { installedAt: 'x', allowUpdate: true, latestVersion: null, version: '1.0' },
                false,
            ],
            [
                'update',
                { installedAt: 'x', allowUpdate: true, latestVersion: '', version: '1.0' },
                false,
            ],
            [
                'uninstall',
                { installedAt: 'x' },
                true,
            ],
            [
                'uninstall',
                { installedAt: null },
                false,
            ],
            [
                'foo',
                { installedAt: 'x' },
                false,
            ],
        ])('should compute actionApplies(%s) as %j -> %s', async (action, extension, expected) => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.actionApplies(action, extension)).toBe(expected);
        });
    });

    describe('bulk operations: applicableCounts and canManage', () => {
        it('should compute applicableCounts over the selected extensions when canManage is true', async () => {
            setMyExtensions([
                { name: 'install-only', installedAt: null, updatedAt: null },
                { name: 'activate-only', installedAt: 'x', active: false, allowUpdate: false, updatedAt: null },
                {
                    name: 'deactivate-only',
                    installedAt: 'x',
                    active: true,
                    allowDisable: true,
                    allowUpdate: false,
                    updatedAt: null,
                },
                {
                    name: 'update-only',
                    installedAt: 'x',
                    active: true,
                    allowDisable: false,
                    allowUpdate: true,
                    latestVersion: '2',
                    version: '1',
                    updatedAt: null,
                },
            ]);
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'install-only' }, true);
            wrapper.vm.onSelectChange({ name: 'activate-only' }, true);
            wrapper.vm.onSelectChange({ name: 'deactivate-only' }, true);
            wrapper.vm.onSelectChange({ name: 'update-only' }, true);

            expect(wrapper.vm.applicableCounts).toEqual({
                install: 1,
                activate: 1,
                deactivate: 1,
                update: 1,
                uninstall: 3,
            });
        });

        it('should zero all applicableCounts when canManage is false via acl', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ aclCan: () => false });

            wrapper.vm.onSelectChange({ name: 'A' }, true);

            expect(wrapper.vm.canManage).toBe(false);
            expect(wrapper.vm.applicableCounts).toEqual({
                install: 0,
                activate: 0,
                deactivate: 0,
                update: 0,
                uninstall: 0,
            });
        });

        it('should zero all applicableCounts when extension management is disabled', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            Shopware.Store.get('context').app.config.settings.disableExtensionManagement = true;
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);

            expect(wrapper.vm.extensionManagementDisabled).toBe(true);
            expect(wrapper.vm.canManage).toBe(false);
            expect(wrapper.vm.applicableCounts).toEqual({
                install: 0,
                activate: 0,
                deactivate: 0,
                update: 0,
                uninstall: 0,
            });
        });
    });

    describe('bulk operations: runCardAction dispatch', () => {
        it.each([
            [
                'install',
                'installExtension',
                [],
            ],
            [
                'activate',
                'activateExtension',
                [],
            ],
            [
                'deactivate',
                'deactivateExtension',
                [],
            ],
            [
                'update',
                'updateExtension',
                [],
            ],
            [
                'uninstall',
                'closeModalAndUninstallExtension',
                [false],
            ],
        ])('should dispatch runCardAction(%s) to card.%s', async (action, method, expectedArgs) => {
            const wrapper = await createWrapper();
            const card = cardActionFns();
            wrapper.vm.cardRefs = { Foo: card };

            await wrapper.vm.runCardAction(action, { name: 'Foo' });

            const callArgs = Object.fromEntries(
                Object.entries(card).map(
                    ([
                        name,
                        fn,
                    ]) => [
                        name,
                        fn.mock.calls,
                    ],
                ),
            );
            const expectedCallArgs = {
                installExtension: [],
                activateExtension: [],
                deactivateExtension: [],
                updateExtension: [],
                closeModalAndUninstallExtension: [],
                [method]: [expectedArgs],
            };

            expect(callArgs).toEqual(expectedCallArgs);
        });

        it('should resolve runCardAction when no card ref exists', async () => {
            const wrapper = await createWrapper();
            wrapper.vm.cardRefs = {};

            await expect(wrapper.vm.runCardAction('install', { name: 'Absent' })).resolves.toBeUndefined();
        });

        it('should resolve runCardAction for an unknown action without calling any card method', async () => {
            const wrapper = await createWrapper();
            const card = cardActionFns();
            wrapper.vm.cardRefs = { Foo: card };

            await expect(wrapper.vm.runCardAction('foo', { name: 'Foo' })).resolves.toBeUndefined();
            expect(card.installExtension).not.toHaveBeenCalled();
        });
    });

    describe('bulk operations: runBulkAction', () => {
        it('should not run a bulk action when canManage is false', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ aclCan: () => false });
            const card = cardActionFns();
            wrapper.vm.cardRefs = { A: card };
            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(card.installExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should not run a bulk action when one is already running', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();
            const card = cardActionFns();
            wrapper.vm.cardRefs = { A: card };
            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.isBulkRunning = true;
            await wrapper.vm.runBulkAction('install');

            expect(card.installExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(true);
        });

        it('should not run a bulk action when no selected extension matches the action', async () => {
            setMyExtensions([{ name: 'A', installedAt: 'x', updatedAt: null }]);
            const wrapper = await createWrapper();
            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.selectedNames).toEqual(['A']);
        });

        it('should run the bulk action for every applicable selected extension, then clear, clear cache and reload', async () => {
            const installedNames = [];
            const cardStub = makeCardStub({
                methods: {
                    installExtension() {
                        installedNames.push(this.extension.name);

                        return Promise.resolve();
                    },
                },
            });

            setMyExtensions([
                { name: 'A', label: 'A', installedAt: null, updatedAt: null },
                { name: 'B', label: 'B', installedAt: null, updatedAt: null },
                { name: 'C', label: 'C', installedAt: 'x', updatedAt: null },
            ]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            wrapper.vm.onSelectChange({ name: 'C' }, true);

            await wrapper.vm.runBulkAction('install');

            expect(installedNames).toEqual([
                'A',
                'B',
            ]);

            expect(wrapper.vm.selectedNames).toEqual([]);
            expect(wrapper.vm.cacheApiService.clear).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should drive the real card ref registered via the template ref callback', async () => {
            const fns = cardActionFns();
            const richCardStub = makeCardStub({ methods: fns, deferredClass: true });

            setMyExtensions([{ name: 'Solo', label: 'Solo', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub: richCardStub });
            await flushPromises();

            expect(typeof wrapper.vm.cardRefs.Solo.installExtension).toBe('function');

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Solo' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(fns.installExtension).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.selectedNames).toEqual([]);
            expect(wrapper.vm.cacheApiService.clear).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledTimes(1);
        });

        it('should keep deferring the per-card reload until the whole batch is done', async () => {
            let resolveInstall;
            let deferDuringRun = null;
            const cardStub = makeCardStub({
                deferredClass: true,
                methods: {
                    installExtension() {
                        deferDuringRun = this.deferReload;

                        return new Promise((resolve) => {
                            resolveInstall = resolve;
                        });
                    },
                },
            });

            setMyExtensions([{ name: 'Solo', label: 'Solo', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Solo' }, true);
            const run = wrapper.vm.runBulkAction('install');
            await flushPromises();

            expect(deferDuringRun).toBe(true);

            resolveInstall();
            await run;
        });
    });

    describe('bulk operations: template wiring', () => {
        it('should bind defer-reload to isBulkRunning on the cards', async () => {
            const cardStub = makeCardStub({ deferredClass: true });
            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            const card = wrapper.findComponent('.sw-self-maintained-extension-card');
            expect(card.props('deferReload')).toBe(false);

            wrapper.vm.isBulkRunning = true;
            await wrapper.vm.$nextTick();

            expect(card.props('deferReload')).toBe(true);
            expect(card.classes()).toContain('is--deferred');
        });

        it('should pass the selected prop to the card from isSelected', async () => {
            const cardStub = makeCardStub();
            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.$nextTick();

            expect(wrapper.findComponent('.sw-self-maintained-extension-card').props('selected')).toBe(true);
        });

        it('should update the selection when the card emits select-change', async () => {
            const cardStub = makeCardStub({ emits: ['select-change'] });
            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            const card = wrapper.findComponent('.sw-self-maintained-extension-card');

            card.vm.$emit('select-change', true);
            await wrapper.vm.$nextTick();
            expect(wrapper.vm.selectedNames).toEqual(['A']);

            card.vm.$emit('select-change', false);
            await wrapper.vm.$nextTick();
            expect(wrapper.vm.selectedNames).toEqual([]);
        });

        it('should show the listing controls and hide the bulk bar when nothing is selected', async () => {
            setMyExtensions([{ name: 'A', label: 'A', installedAt: 'x', updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-extension-bulk-actions-bar').exists()).toBe(false);
            expect(wrapper.find('.sw-extension-my-extensions-listing-controls').exists()).toBe(true);
        });

        it('should swap to the bulk bar when a selection exists, passing counts and disabled', async () => {
            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.$nextTick();

            const bar = wrapper.findComponent('.sw-extension-bulk-actions-bar');
            expect(bar.exists()).toBe(true);
            expect(wrapper.find('.sw-extension-my-extensions-listing-controls').exists()).toBe(false);
            expect(bar.props('selectedCount')).toBe(1);
            expect(bar.props('applicableCounts')).toEqual(wrapper.vm.applicableCounts);
            expect(bar.props('disabled')).toBe(false);
        });

        it('should wire the bulk bar select-all and clear events to the page methods', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', installedAt: null, updatedAt: null },
                { name: 'B', label: 'B', installedAt: null, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.$nextTick();

            const bar = wrapper.findComponent('.sw-extension-bulk-actions-bar');

            bar.vm.$emit('select-all');
            await wrapper.vm.$nextTick();
            expect(wrapper.vm.selectedNames).toEqual([
                'A',
                'B',
            ]);

            bar.vm.$emit('clear');
            await wrapper.vm.$nextTick();
            expect(wrapper.vm.selectedNames).toEqual([]);
        });

        it('should wire the bulk bar run-action event through runBulkAction to the cards', async () => {
            const fns = cardActionFns();
            const cardStub = makeCardStub({ methods: fns });

            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.$nextTick();

            const bar = wrapper.findComponent('.sw-extension-bulk-actions-bar');
            bar.vm.$emit('run-action', 'install');
            await flushPromises();

            expect(fns.installExtension).toHaveBeenCalledTimes(1);
        });
    });

    describe('bulk operations: selection-clearing watchers', () => {
        it('should clear selection, reset the active filter and reload the list when the route name changes', async () => {
            setMyExtensions([{ name: 'A', label: 'A', installedAt: 'x', updatedAt: null }]);
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.filterByActiveState = true;
            shopwareService.updateExtensionData.mockClear();

            await wrapper.vm.$router.push(routes[1]);
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.selectedNames).toEqual([]);
            expect(wrapper.vm.filterByActiveState).toBe(false);
            expect(shopwareService.updateExtensionData).toHaveBeenCalled();
        });

        it('should clear selection when the search term query changes', async () => {
            setMyExtensions([{ name: 'A', label: 'A', installedAt: 'x', updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            expect(wrapper.vm.selectedNames).toEqual(['A']);

            await wrapper.vm.$router.push({ name: wrapper.vm.$route.name, query: { term: 'x' } });
            await flushPromises();

            expect(wrapper.vm.selectedNames).toEqual([]);
        });

        it('should clear selection when the page query changes', async () => {
            const extensions = Array(40)
                .fill()
                .map((_, i) => ({
                    name: `extension-${i}`,
                    label: `extension-${i}`,
                    installedAt: `foo-${i}`,
                    updatedAt: null,
                }));
            setMyExtensions(extensions);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.selectAllVisible();
            expect(wrapper.vm.selectedNames.length).toBeGreaterThan(0);

            await wrapper.vm.$router.push({ name: wrapper.vm.$route.name, query: { page: 2 } });
            await flushPromises();

            expect(wrapper.vm.selectedNames).toEqual([]);
        });

        it('should keep the selection when only the sorting option changes', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', installedAt: 'x', updatedAt: null },
                { name: 'B', label: 'B', installedAt: 'x', updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.changeSortingOption('name-asc');
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.selectedNames).toEqual(['A']);
        });

        it('should clear the selection when the active-state filter changes', async () => {
            const wrapper = await createWrapper();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.changeActiveState(true);
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.selectedNames).toEqual([]);
            expect(wrapper.vm.filterByActiveState).toBe(true);
        });
    });

    describe('bulk operations: registerCardRef', () => {
        it('should store and delete card refs via registerCardRef', async () => {
            setMyExtensions([]);
            const wrapper = await createWrapper();

            expect(wrapper.vm.cardRefs).toEqual({});

            wrapper.vm.registerCardRef('X', { foo: 1 });
            expect(wrapper.vm.cardRefs.X).toEqual({ foo: 1 });

            wrapper.vm.registerCardRef('X', null);
            expect(wrapper.vm.cardRefs.X).toBeUndefined();
        });
    });
});
