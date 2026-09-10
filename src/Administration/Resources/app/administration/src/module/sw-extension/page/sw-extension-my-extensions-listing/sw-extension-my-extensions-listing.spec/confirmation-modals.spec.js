/**
 * @sw-package checkout
 */

import {
    createWrapper,
    setMyExtensions,
    setupListingHooks,
    shopwareService,
} from './sw-extension-my-extensions-listing.fixtures';

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    setupListingHooks();

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
                {
                    name: 'Free',
                    label: 'Free',
                    type: 'app',
                    installedAt: 'x',
                    active: true,
                    allowDisable: true,
                    updatedAt: null,
                },
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
                {
                    name: 'Free',
                    label: 'Free',
                    type: 'theme',
                    installedAt: 'x',
                    active: true,
                    allowDisable: true,
                    updatedAt: null,
                },
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
});
