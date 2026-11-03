/**
 * @sw-package checkout
 */

import {
    consentError,
    createWrapper,
    extensionStoreActionService,
    setMyExtensions,
    setupListingHooks,
    shopwareService,
} from './sw-extension-my-extensions-listing.fixtures';

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    setupListingHooks();

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
});
