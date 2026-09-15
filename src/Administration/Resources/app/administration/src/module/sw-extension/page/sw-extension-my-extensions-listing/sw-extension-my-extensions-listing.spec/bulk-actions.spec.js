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
});
