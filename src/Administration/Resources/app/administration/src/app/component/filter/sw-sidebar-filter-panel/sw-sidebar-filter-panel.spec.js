/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('components/sw-sidebar-filter-panel', () => {
    async function createWrapper(activeFilterNumber = 0) {
        return mount(await wrapTestComponent('sw-sidebar-filter-panel', { sync: true }), {
            props: {
                activeFilterNumber,
            },
            global: {
                stubs: {
                    'sw-sidebar-item': await wrapTestComponent('sw-sidebar-item', { sync: true }),
                    'sw-filter-panel': true,
                    'mt-icon': true,
                },
            },
        });
    }

    it('should register the open filters shortcut', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterPanel');
    });

    it('should show the open filters shortcut in the sidebar item', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.filterSidebarItem?.tooltipShortcut).toEqual([
            'O',
            'F',
        ]);
    });

    it('should open the filter panel', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.filterSidebarItem).not.toBeNull();
        const openContentSpy = jest.spyOn(wrapper.vm.filterSidebarItem, 'openContent');

        wrapper.vm.openFilterPanel();

        expect(openContentSpy).toHaveBeenCalledTimes(1);
    });

    it('should reset all active filters', async () => {
        const wrapper = await createWrapper(1);
        await flushPromises();

        wrapper.vm.filterSidebarItem.isActive = true;
        await wrapper.vm.$nextTick();

        const resetAllSpy = jest.fn();
        wrapper.vm.$refs.filterPanel.resetAll = resetAllSpy;

        await wrapper.find('a').trigger('click');

        expect(resetAllSpy).toHaveBeenCalledTimes(1);
    });
});
