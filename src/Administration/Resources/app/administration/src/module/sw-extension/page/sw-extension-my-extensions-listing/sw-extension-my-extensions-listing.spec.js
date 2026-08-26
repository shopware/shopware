import { mount, config } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';
import ShopwareService from 'src/module/sw-extension/service/shopware-extension.service';
import 'src/module/sw-extension/mixin/sw-extension-error.mixin';
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
shopwareService.installExtension = jest.fn(() => Promise.resolve());
shopwareService.installAndActivateExtension = jest.fn(() => Promise.resolve());
shopwareService.activateExtension = jest.fn(() => Promise.resolve());
shopwareService.deactivateExtension = jest.fn(() => Promise.resolve());
shopwareService.uninstallExtension = jest.fn(() => Promise.resolve());
shopwareService.updateExtension = jest.fn(() => Promise.resolve());

const extensionStoreActionService = {
    downloadExtension: jest.fn(() => Promise.resolve()),
};

// The page uses the sw-extension-error mixin, which resolves this service to map error responses.
if (!Shopware.Service().list().includes('extensionErrorService')) {
    Shopware.Service().register('extensionErrorService', () => ({
        handleErrorResponse: jest.fn(() => []),
    }));
}

// Consent error the backend throws on update when an extension requires new permissions.
function consentError(deltas = { permissions: {}, domains: [] }) {
    return {
        response: {
            data: {
                errors: [
                    {
                        code: 'FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION',
                        meta: { parameters: { deltas } },
                    },
                ],
            },
        },
    };
}

function setMyExtensions(extensions) {
    Shopware.Store.get('shopwareExtensions').setMyExtensions(extensions);
}

function makeCardStub({ emits = [] } = {}) {
    return {
        template: '<div class="sw-self-maintained-extension-card">{{ extension.label }}</div>',
        props: [
            'extension',
            'selected',
            'bulkLoading',
        ],
        emits,
    };
}

async function createWrapper({ aclCan = () => true, cardStub, query = {} } = {}) {
    delete config.global.mocks.$router;
    delete config.global.mocks.$route;

    const router = createRouter({
        routes,
        history: createWebHashHistory(),
    });

    await router.push({ ...routes[0], query });
    await router.isReady();

    return mount(
        await wrapTestComponent('sw-extension-my-extensions-listing', {
            sync: true,
        }),
        {
            global: {
                plugins: [router],
                // The page declares the sw-extension-error mixin by name, resolve it explicitly for the test.
                mixins: [Shopware.Mixin.getByName('sw-extension-error')],
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
                    'sw-extension-permissions-modal': {
                        template: '<div class="sw-extension-permissions-modal" />',
                        props: [
                            'permissions',
                            'domains',
                            'title',
                            'description',
                            'actionLabel',
                            'extensionLabel',
                        ],
                    },
                    'sw-extension-bulk-uninstall-modal': {
                        template: '<div class="sw-extension-bulk-uninstall-modal" />',
                        props: [
                            'extensions',
                            'isLoading',
                        ],
                    },
                    'sw-extension-bulk-deactivation-modal': {
                        template: '<div class="sw-extension-bulk-deactivation-modal" />',
                        props: [
                            'extensions',
                            'isLoading',
                        ],
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {};
                        },
                    },
                    shopwareExtensionService: shopwareService,
                    extensionStoreActionService,
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
        shopwareService.installExtension.mockClear();
        shopwareService.installExtension.mockResolvedValue(undefined);
        shopwareService.installAndActivateExtension.mockClear();
        shopwareService.installAndActivateExtension.mockResolvedValue(undefined);
        shopwareService.activateExtension.mockClear();
        shopwareService.activateExtension.mockResolvedValue(undefined);
        shopwareService.deactivateExtension.mockClear();
        shopwareService.deactivateExtension.mockResolvedValue(undefined);
        shopwareService.uninstallExtension.mockClear();
        shopwareService.uninstallExtension.mockResolvedValue(undefined);
        shopwareService.updateExtension.mockClear();
        shopwareService.updateExtension.mockResolvedValue(undefined);
        extensionStoreActionService.downloadExtension.mockClear();
        extensionStoreActionService.downloadExtension.mockResolvedValue(undefined);
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

    describe('bulk operations: runExtensionAction (service dispatch + result contract)', () => {
        it('should install AND activate a extension without permissions, downloading first for store extensions', async () => {
            // Without permissions, openPermissionsModalForInstall installs and activates.
            const wrapper = await createWrapper();

            const result = await wrapper.vm.runExtensionAction('install', { name: 'Foo', type: 'app', source: 'store' });

            expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledWith('Foo');
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('Foo', 'app');
            expect(shopwareService.installExtension).not.toHaveBeenCalled();
            expect(result.status).toBe('success');
        });

        it('should install only (no activation) an extension that declares permissions', async () => {
            // An accepted permissions modal installs without activating.
            const wrapper = await createWrapper();

            const result = await wrapper.vm.runExtensionAction('install', {
                name: 'Foo',
                type: 'app',
                source: 'store',
                permissions: { product: [{ entity: 'product', operation: 'read' }] },
            });

            expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledWith('Foo');
            expect(shopwareService.installExtension).toHaveBeenCalledWith('Foo', 'app');
            expect(shopwareService.installAndActivateExtension).not.toHaveBeenCalled();
            expect(result.status).toBe('success');
        });

        it('should not download for a non-store install', async () => {
            const wrapper = await createWrapper();

            await wrapper.vm.runExtensionAction('install', { name: 'Foo', type: 'app', source: 'local' });

            expect(extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('Foo', 'app');
        });

        it('should activate via the service', async () => {
            const wrapper = await createWrapper();

            await wrapper.vm.runExtensionAction('activate', { name: 'Foo', type: 'app' });

            expect(shopwareService.activateExtension).toHaveBeenCalledWith('Foo', 'app');
        });

        it('should deactivate via the service', async () => {
            const wrapper = await createWrapper();

            await wrapper.vm.runExtensionAction('deactivate', { name: 'Foo', type: 'app' });

            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('Foo', 'app');
        });

        it('should report a failed result and surface the error on a non-consent failure', async () => {
            const wrapper = await createWrapper();
            const showErrors = jest.spyOn(wrapper.vm, 'showExtensionErrors');
            shopwareService.installAndActivateExtension.mockRejectedValue({
                response: { data: { errors: [{ code: 'SOME_ERROR' }] } },
            });

            const result = await wrapper.vm.runExtensionAction('install', { name: 'Foo', type: 'app' });

            expect(result.status).toBe('failed');
            expect(showErrors).toHaveBeenCalled();
        });

        it('should report requiresConsent with the deltas on an update consent error', async () => {
            const wrapper = await createWrapper();
            const deltas = { permissions: { order: [{ entity: 'order', operation: 'read' }] }, domains: ['x.example.com'] };
            shopwareService.updateExtension.mockRejectedValue(consentError(deltas));

            const result = await wrapper.vm.runExtensionAction('update', { name: 'Foo', type: 'app', installedAt: 'x' });

            expect(result.status).toBe('requiresConsent');
            expect(result.deltas).toEqual(deltas);
        });

        it('should resolve success for an unknown action without calling any service', async () => {
            const wrapper = await createWrapper();

            const result = await wrapper.vm.runExtensionAction('foo', { name: 'Foo', type: 'app' });

            expect(result.status).toBe('success');
            expect(shopwareService.installExtension).not.toHaveBeenCalled();
        });
    });

    describe('bulk operations: runBulkAction', () => {
        it('should not run a bulk action when canManage is false', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ aclCan: () => false });
            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(shopwareService.installExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should not run a bulk action when one is already running', async () => {
            setMyExtensions([{ name: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();
            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.isBulkRunning = true;
            await wrapper.vm.runBulkAction('install');

            expect(shopwareService.installExtension).not.toHaveBeenCalled();
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

        it('should run the action for every applicable selected extension, then clear, clear cache and reload', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: null, updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: null, updatedAt: null },
                { name: 'C', label: 'C', type: 'app', installedAt: 'x', updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            wrapper.vm.onSelectChange({ name: 'C' }, true);

            await wrapper.vm.runBulkAction('install');

            // Only the applicable (not installed) A and B install. C is already installed.
            // Both are permissionless, so they install and activate like a single card install.
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('A', 'app');
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('B', 'app');
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledTimes(2);

            expect(wrapper.vm.selectedNames).toEqual([]);
            expect(wrapper.vm.cacheApiService.clear).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should activate every applicable selected extension via the service', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: 'x', active: false, updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: 'x', active: false, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('activate');

            expect(shopwareService.activateExtension).toHaveBeenCalledWith('A', 'app');
            expect(shopwareService.activateExtension).toHaveBeenCalledWith('B', 'app');
        });

        it('should deactivate only deactivatable selected extensions via the service', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: 'x', active: true, allowDisable: true, updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: 'x', active: true, allowDisable: false, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('deactivate');

            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('A', 'app');
            expect(shopwareService.deactivateExtension).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.showBulkDeactivationModal).toBe(false);
        });

        it('should surface per item errors and still reload after the batch completes', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: null, updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: null, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            const showErrors = jest.spyOn(wrapper.vm, 'showExtensionErrors');
            shopwareService.installAndActivateExtension.mockImplementation((name) => {
                if (name === 'A') {
                    return Promise.reject({ response: { data: { errors: [{ code: 'BOOM' }] } } });
                }
                return Promise.resolve();
            });

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('install');

            // A failed but B still installed. The error was surfaced and the batch finalized once.
            expect(showErrors).toHaveBeenCalled();
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('B', 'app');
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should NOT reload or clear the cache when every item of the batch failed', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: null, updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: null, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            const showErrors = jest.spyOn(wrapper.vm, 'showExtensionErrors');
            shopwareService.installAndActivateExtension.mockRejectedValue({
                response: { data: { errors: [{ code: 'BOOM' }] } },
            });

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(showErrors).toHaveBeenCalledTimes(2);
            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should flag each processing extension as bulk-loading while its action is in flight', async () => {
            let resolveInstall;
            setMyExtensions([{ name: 'Solo', label: 'Solo', type: 'app', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            shopwareService.installAndActivateExtension.mockImplementation(
                () =>
                    new Promise((resolve) => {
                        resolveInstall = resolve;
                    }),
            );

            wrapper.vm.onSelectChange({ name: 'Solo' }, true);
            const run = wrapper.vm.runBulkAction('install');
            await flushPromises();

            // While the service call is pending, the card is marked busy so it shows the loader.
            expect(wrapper.vm.bulkProcessingNames).toContain('Solo');

            resolveInstall();
            await run;

            // Cleared once the batch finalizes.
            expect(wrapper.vm.bulkProcessingNames).toEqual([]);
        });
    });

    describe('bulk operations: aggregateConsent', () => {
        it('should merge categories, de-duplicate entity+operation and union domains', async () => {
            const wrapper = await createWrapper();

            const result = wrapper.vm.aggregateConsent([
                {
                    permissions: {
                        product: [
                            { entity: 'product', operation: 'read' },
                            { entity: 'product', operation: 'update' },
                        ],
                    },
                    domains: ['a.example.com'],
                },
                {
                    permissions: {
                        product: [
                            { entity: 'product', operation: 'read' },
                        ],
                        order: [{ entity: 'order', operation: 'read' }],
                    },
                    domains: [
                        'a.example.com',
                        'b.example.com',
                    ],
                },
            ]);

            expect(result.permissions).toEqual({
                product: [
                    { entity: 'product', operation: 'read' },
                    { entity: 'product', operation: 'update' },
                ],
                order: [{ entity: 'order', operation: 'read' }],
            });
            expect(result.domains).toEqual([
                'a.example.com',
                'b.example.com',
            ]);
        });

        it('should treat a missing permissions/domains field as empty', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.aggregateConsent([{ name: 'A' }])).toEqual({ permissions: {}, domains: [] });
        });
    });

    describe('bulk operations: install consent', () => {
        it('should open the aggregated consent modal and NOT install before the user accepts', async () => {
            setMyExtensions([
                {
                    name: 'A',
                    label: 'A',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: { product: [{ entity: 'product', operation: 'read' }] },
                    domains: ['a.example.com'],
                },
                {
                    name: 'B',
                    label: 'B',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: { order: [{ entity: 'order', operation: 'read' }] },
                    domains: [],
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(wrapper.vm.showBulkConsentModal).toBe(true);
            expect(wrapper.vm.bulkConsent.action).toBe('install');
            expect(wrapper.vm.bulkConsent.permissions).toEqual({
                product: [{ entity: 'product', operation: 'read' }],
                order: [{ entity: 'order', operation: 'read' }],
            });
            expect(wrapper.vm.bulkConsent.domains).toEqual(['a.example.com']);
            expect(shopwareService.installExtension).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(true);

            await wrapper.vm.onBulkConsentAccept();

            expect(shopwareService.installExtension).toHaveBeenCalledWith('A', 'app');
            expect(shopwareService.installExtension).toHaveBeenCalledWith('B', 'app');
            expect(shopwareService.installAndActivateExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.cacheApiService.clear).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
            expect(wrapper.vm.showBulkConsentModal).toBe(false);
        });

        it('should install only permission extensions and install + activate permissionless ones in a mixed batch', async () => {
            setMyExtensions([
                {
                    name: 'WithPerms',
                    label: 'WithPerms',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: { product: [{ entity: 'product', operation: 'read' }] },
                    domains: [],
                },
                {
                    name: 'NoPerms',
                    label: 'NoPerms',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: {},
                    domains: [],
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'WithPerms' }, true);
            wrapper.vm.onSelectChange({ name: 'NoPerms' }, true);
            await wrapper.vm.runBulkAction('install');

            // One extension declares permissions, so the aggregated modal gates the whole batch.
            expect(wrapper.vm.showBulkConsentModal).toBe(true);

            await wrapper.vm.onBulkConsentAccept();

            // Per item single card parity:
            // accepted permissions -> install only
            // none -> install and activate
            expect(shopwareService.installExtension).toHaveBeenCalledWith('WithPerms', 'app');
            expect(shopwareService.installExtension).toHaveBeenCalledTimes(1);
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('NoPerms', 'app');
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledTimes(1);
        });

        it('should install AND activate directly without a modal when no selected extension requires consent', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: null, updatedAt: null, permissions: {}, domains: [] },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(wrapper.vm.showBulkConsentModal).toBe(false);
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('A', 'app');
            expect(shopwareService.installExtension).not.toHaveBeenCalled();
            expect(reload).toHaveBeenCalledTimes(1);
        });

        it('should install directly without a modal for a domains only extension', async () => {
            setMyExtensions([
                {
                    name: 'A',
                    label: 'A',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: {},
                    domains: ['a.example.com'],
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');

            expect(wrapper.vm.showBulkConsentModal).toBe(false);
            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('A', 'app');
        });

        it('should install nothing, keep the selection and NOT reload when the consent modal is cancelled', async () => {
            setMyExtensions([
                {
                    name: 'A',
                    label: 'A',
                    type: 'app',
                    installedAt: null,
                    updatedAt: null,
                    permissions: { product: [{ entity: 'product', operation: 'read' }] },
                    domains: [],
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('install');
            expect(wrapper.vm.showBulkConsentModal).toBe(true);

            await wrapper.vm.onBulkConsentCancel();
            await flushPromises();

            expect(shopwareService.installExtension).not.toHaveBeenCalled();
            expect(shopwareService.installAndActivateExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.showBulkConsentModal).toBe(false);
            expect(wrapper.vm.bulkConsent).toBeNull();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.cacheApiService.clear).not.toHaveBeenCalled();
            expect(wrapper.vm.selectedNames).toEqual(['A']);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });
    });

    describe('bulk operations: update consent', () => {
        it('should apply clean updates, then open the consent modal only for the delta items', async () => {
            setMyExtensions([
                {
                    name: 'Clean',
                    label: 'Clean',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
                {
                    name: 'NeedsConsent',
                    label: 'NeedsConsent',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            shopwareService.updateExtension.mockImplementation((name) => {
                if (name === 'NeedsConsent') {
                    return Promise.reject(
                        consentError({
                            permissions: { order: [{ entity: 'order', operation: 'read' }] },
                            domains: ['x.example.com'],
                        }),
                    );
                }
                return Promise.resolve();
            });

            wrapper.vm.onSelectChange({ name: 'Clean' }, true);
            wrapper.vm.onSelectChange({ name: 'NeedsConsent' }, true);
            await wrapper.vm.runBulkAction('update');
            await flushPromises();

            // First pass: both attempted with allowNewPermissions=false.
            expect(shopwareService.updateExtension).toHaveBeenCalledWith('Clean', 'app', false);
            expect(shopwareService.updateExtension).toHaveBeenCalledWith('NeedsConsent', 'app', false);

            // Consent modal opened for the delta item only.
            expect(wrapper.vm.showBulkConsentModal).toBe(true);
            expect(wrapper.vm.bulkConsent.action).toBe('update');
            expect(wrapper.vm.bulkConsent.items.map((item) => item.name)).toEqual(['NeedsConsent']);
            expect(wrapper.vm.bulkConsent.permissions).toEqual({ order: [{ entity: 'order', operation: 'read' }] });
            expect(wrapper.vm.bulkConsent.domains).toEqual(['x.example.com']);
            expect(wrapper.vm.isBulkRunning).toBe(true);
            expect(reload).not.toHaveBeenCalled();

            await wrapper.vm.onBulkConsentAccept();

            // On accept the delta item is re-run with allowNewPermissions=true.
            expect(shopwareService.updateExtension).toHaveBeenCalledWith('NeedsConsent', 'app', true);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should NOT apply new privileges and NOT reload when cancelled and no clean update applied', async () => {
            setMyExtensions([
                {
                    name: 'NeedsConsent',
                    label: 'NeedsConsent',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            shopwareService.updateExtension.mockRejectedValue(consentError());

            wrapper.vm.onSelectChange({ name: 'NeedsConsent' }, true);
            await wrapper.vm.runBulkAction('update');
            await flushPromises();

            expect(wrapper.vm.showBulkConsentModal).toBe(true);
            shopwareService.updateExtension.mockClear();

            await wrapper.vm.onBulkConsentCancel();
            await flushPromises();

            expect(shopwareService.updateExtension).not.toHaveBeenCalledWith('NeedsConsent', 'app', true);
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.selectedNames).toEqual(['NeedsConsent']);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should NOT apply new privileges but DO reload on cancel when a clean update already applied', async () => {
            setMyExtensions([
                {
                    name: 'Clean',
                    label: 'Clean',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
                {
                    name: 'NeedsConsent',
                    label: 'NeedsConsent',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            shopwareService.updateExtension.mockImplementation((name) => {
                if (name === 'NeedsConsent') {
                    return Promise.reject(consentError());
                }
                return Promise.resolve();
            });

            wrapper.vm.onSelectChange({ name: 'Clean' }, true);
            wrapper.vm.onSelectChange({ name: 'NeedsConsent' }, true);
            await wrapper.vm.runBulkAction('update');
            await flushPromises();

            expect(wrapper.vm.showBulkConsentModal).toBe(true);
            shopwareService.updateExtension.mockClear();

            await wrapper.vm.onBulkConsentCancel();
            await flushPromises();

            expect(shopwareService.updateExtension).not.toHaveBeenCalledWith('NeedsConsent', 'app', true);
            // The clean update already applied during preflight. Reload so the list reflects it.
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should surface a non consent update error and not open the consent modal', async () => {
            setMyExtensions([
                {
                    name: 'Boom',
                    label: 'Boom',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});
            const showErrors = jest.spyOn(wrapper.vm, 'showExtensionErrors');
            shopwareService.updateExtension.mockRejectedValue({
                response: { data: { errors: [{ code: 'SOME_OTHER_ERROR' }] } },
            });

            wrapper.vm.onSelectChange({ name: 'Boom' }, true);
            await wrapper.vm.runBulkAction('update');
            await flushPromises();

            expect(showErrors).toHaveBeenCalled();
            expect(wrapper.vm.showBulkConsentModal).toBe(false);
            // The only item failed -> nothing changed -> the run finalizes without a reload.
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should download store updates before attempting the update', async () => {
            setMyExtensions([
                {
                    name: 'Store',
                    label: 'Store',
                    type: 'app',
                    installedAt: 'x',
                    updatedAt: null,
                    allowUpdate: true,
                    version: '1.0.0',
                    latestVersion: '2.0.0',
                    updateSource: 'store',
                },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Store' }, true);
            await wrapper.vm.runBulkAction('update');
            await flushPromises();

            expect(extensionStoreActionService.downloadExtension).toHaveBeenCalledWith('Store');
        });
    });

    describe('bulk operations: uninstall confirmation', () => {
        it('should open the bulk uninstall modal and uninstall nothing before confirmation', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', type: 'app', installedAt: 'x', updatedAt: null },
                { name: 'B', label: 'B', type: 'app', installedAt: 'x', updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.onSelectChange({ name: 'B' }, true);
            await wrapper.vm.runBulkAction('uninstall');

            expect(wrapper.vm.showBulkUninstallModal).toBe(true);
            expect(wrapper.vm.bulkUninstallItems.map((item) => item.name)).toEqual([
                'A',
                'B',
            ]);
            expect(shopwareService.uninstallExtension).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(true);
        });

        it.each([
            true,
            false,
        ])(
            'should uninstall every item via the service with the batch-wide removeData=%s on confirm',
            async (removeData) => {
                setMyExtensions([
                    { name: 'A', label: 'A', type: 'app', installedAt: 'x', updatedAt: null },
                    { name: 'B', label: 'B', type: 'theme', installedAt: 'x', updatedAt: null },
                ]);
                const wrapper = await createWrapper();
                await flushPromises();

                const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

                wrapper.vm.onSelectChange({ name: 'A' }, true);
                wrapper.vm.onSelectChange({ name: 'B' }, true);
                await wrapper.vm.runBulkAction('uninstall');

                await wrapper.vm.confirmBulkUninstall(removeData);

                expect(shopwareService.uninstallExtension).toHaveBeenCalledWith('A', 'app', removeData);
                expect(shopwareService.uninstallExtension).toHaveBeenCalledWith('B', 'theme', removeData);
                expect(wrapper.vm.showBulkUninstallModal).toBe(false);
                expect(reload).toHaveBeenCalledTimes(1);
                expect(wrapper.vm.isBulkRunning).toBe(false);
            },
        );

        it('should uninstall nothing and reset the run when the confirmation is cancelled', async () => {
            setMyExtensions([{ name: 'A', label: 'A', type: 'app', installedAt: 'x', updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.runBulkAction('uninstall');

            wrapper.vm.cancelBulkUninstall();

            expect(shopwareService.uninstallExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.showBulkUninstallModal).toBe(false);
            expect(wrapper.vm.bulkUninstallItems).toEqual([]);
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });
    });

    describe('bulk operations: deactivation confirmation', () => {
        function rentedExtension(name) {
            return {
                name,
                label: name,
                type: 'app',
                source: 'store',
                installedAt: 'x',
                active: true,
                allowDisable: true,
                updatedAt: null,
                storeLicense: { variant: 'rent', expired: false },
            };
        }

        it('should open the deactivation modal for a rented extension and deactivate nothing before confirmation', async () => {
            setMyExtensions([rentedExtension('Rented')]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Rented' }, true);
            await wrapper.vm.runBulkAction('deactivate');

            expect(wrapper.vm.showBulkDeactivationModal).toBe(true);
            expect(wrapper.vm.bulkDeactivationItems.map((item) => item.name)).toEqual(['Rented']);
            expect(shopwareService.deactivateExtension).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(true);
        });

        it('should list only the rented extensions in the modal for a mixed batch', async () => {
            setMyExtensions([
                rentedExtension('Rented'),
                { name: 'Free', label: 'Free', type: 'app', installedAt: 'x', active: true, allowDisable: true, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Rented' }, true);
            wrapper.vm.onSelectChange({ name: 'Free' }, true);
            await wrapper.vm.runBulkAction('deactivate');

            expect(wrapper.vm.showBulkDeactivationModal).toBe(true);
            expect(wrapper.vm.bulkDeactivationItems.map((item) => item.name)).toEqual([
                'Rented',
                'Free',
            ]);
            expect(wrapper.vm.rentedBulkDeactivationItems.map((item) => item.name)).toEqual(['Rented']);
            expect(shopwareService.deactivateExtension).not.toHaveBeenCalled();
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(true);
        });

        it('should deactivate the whole batch on confirm, including the non-rented extensions', async () => {
            setMyExtensions([
                rentedExtension('Rented'),
                { name: 'Free', label: 'Free', type: 'theme', installedAt: 'x', active: true, allowDisable: true, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Rented' }, true);
            wrapper.vm.onSelectChange({ name: 'Free' }, true);
            await wrapper.vm.runBulkAction('deactivate');

            await wrapper.vm.confirmBulkDeactivation();

            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('Rented', 'app');
            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('Free', 'theme');
            expect(shopwareService.deactivateExtension).toHaveBeenCalledTimes(2);
            expect(wrapper.vm.showBulkDeactivationModal).toBe(false);
            expect(wrapper.vm.bulkDeactivationItems).toEqual([]);
            expect(wrapper.vm.cacheApiService.clear).toHaveBeenCalledTimes(1);
            expect(reload).toHaveBeenCalledTimes(1);
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });

        it('should deactivate nothing and reset the run when the confirmation is cancelled', async () => {
            setMyExtensions([rentedExtension('Rented')]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'Rented' }, true);
            await wrapper.vm.runBulkAction('deactivate');

            wrapper.vm.cancelBulkDeactivation();

            expect(shopwareService.deactivateExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.showBulkDeactivationModal).toBe(false);
            expect(wrapper.vm.bulkDeactivationItems).toEqual([]);
            expect(reload).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);
        });
    });

    describe('bulk operations: template wiring', () => {
        it('should bind bulk loading to the per extension processing state on the cards', async () => {
            const cardStub = makeCardStub();
            setMyExtensions([{ name: 'A', label: 'A', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper({ cardStub });
            await flushPromises();

            const card = wrapper.findComponent('.sw-self-maintained-extension-card');
            expect(card.props('bulkLoading')).toBe(false);

            wrapper.vm.bulkProcessingNames = ['A'];
            await wrapper.vm.$nextTick();

            expect(card.props('bulkLoading')).toBe(true);
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

        it('should wire the bulk bar run action event through runBulkAction to the service', async () => {
            setMyExtensions([{ name: 'A', label: 'A', type: 'app', installedAt: null, updatedAt: null }]);
            const wrapper = await createWrapper();
            await flushPromises();

            jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            await wrapper.vm.$nextTick();

            const bar = wrapper.findComponent('.sw-extension-bulk-actions-bar');
            bar.vm.$emit('run-action', 'install');
            await flushPromises();

            expect(shopwareService.installAndActivateExtension).toHaveBeenCalledWith('A', 'app');
        });

        it('should render the deactivation modal with the rented extensions and wire its events to the handlers', async () => {
            setMyExtensions([
                {
                    name: 'Rented',
                    label: 'Rented',
                    type: 'app',
                    source: 'store',
                    installedAt: 'x',
                    active: true,
                    allowDisable: true,
                    updatedAt: null,
                    storeLicense: { variant: 'rent', expired: false },
                },
                { name: 'Free', label: 'Free', type: 'app', installedAt: 'x', active: true, allowDisable: true, updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            const reload = jest.spyOn(wrapper.vm, '_reloadPage').mockImplementation(() => {});

            expect(wrapper.find('.sw-extension-bulk-deactivation-modal').exists()).toBe(false);

            wrapper.vm.onSelectChange({ name: 'Rented' }, true);
            wrapper.vm.onSelectChange({ name: 'Free' }, true);
            await wrapper.vm.runBulkAction('deactivate');
            await wrapper.vm.$nextTick();

            const modal = wrapper.findComponent('.sw-extension-bulk-deactivation-modal');
            expect(modal.exists()).toBe(true);
            expect(modal.props('extensions').map((extension) => extension.name)).toEqual(['Rented']);

            modal.vm.$emit('modal-close');
            await flushPromises();

            expect(shopwareService.deactivateExtension).not.toHaveBeenCalled();
            expect(wrapper.vm.isBulkRunning).toBe(false);

            await wrapper.vm.runBulkAction('deactivate');
            await wrapper.vm.$nextTick();

            wrapper.findComponent('.sw-extension-bulk-deactivation-modal').vm.$emit('confirm');
            await flushPromises();

            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('Rented', 'app');
            expect(shopwareService.deactivateExtension).toHaveBeenCalledWith('Free', 'app');
            expect(reload).toHaveBeenCalledTimes(1);
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

        it('should clear the selection when the sorting option changes (it can hide a selected item)', async () => {
            setMyExtensions([
                { name: 'A', label: 'A', installedAt: 'x', updatedAt: null },
                { name: 'B', label: 'B', installedAt: 'x', updatedAt: null },
            ]);
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.vm.onSelectChange({ name: 'A' }, true);
            wrapper.vm.changeSortingOption('name-asc');
            await flushPromises();

            expect(wrapper.vm.selectedNames).toEqual([]);
        });

        it('should clear the selection when the page size (limit) changes', async () => {
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

            await wrapper.vm.$router.push({ name: wrapper.vm.$route.name, query: { limit: 10 } });
            await flushPromises();

            expect(wrapper.vm.selectedNames).toEqual([]);
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
});
