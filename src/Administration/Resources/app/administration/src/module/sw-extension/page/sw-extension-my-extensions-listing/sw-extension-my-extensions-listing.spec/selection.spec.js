/**
 * @sw-package checkout
 */

import { createWrapper, setMyExtensions, setupListingHooks } from './sw-extension-my-extensions-listing.fixtures';

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    setupListingHooks();

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
});
