/**
 * @sw-package checkout
 */

import {
    createWrapper,
    makeCardStub,
    routes,
    setMyExtensions,
    setupListingHooks,
    shopwareService,
} from './sw-extension-my-extensions-listing.fixtures';

describe('src/module/sw-extension/page/sw-extension-my-extensions-listing', () => {
    setupListingHooks();

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
