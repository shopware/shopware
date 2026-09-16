/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

const deviceMock = {
    onResize: jest.fn(),
    removeResizeListener: jest.fn(),
};

async function createWrapper(props = {}) {
    return mount(
        await wrapTestComponent('sw-settings-snippet-sidebar', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-sidebar': await wrapTestComponent('sw-sidebar', { sync: true }),
                    'sw-sidebar-item': await wrapTestComponent('sw-sidebar-item', { sync: true }),
                    'sw-sidebar-navigation-item': await wrapTestComponent('sw-sidebar-navigation-item', { sync: true }),
                    'mt-tooltip': true,
                    'sw-settings-snippet-filter-switch': true,
                    'sw-sidebar-collapse': true,
                },
                mocks: {
                    $device: deviceMock,
                },
                provide: {
                    setSwPageSidebarOffset: () => {},
                    removeSwPageSidebarOffset: () => {},
                },
            },
            props: {
                filterItems: [],
                authorFilters: [],
                ...props,
            },
        },
    );
}

describe('sw-settings-snippet-sidebar', () => {
    it('should register the open filters shortcut', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterSidebar');
    });

    it('should open the filter sidebar via the open-filters shortcut', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-sidebar').classes()).not.toContain('is--opened');

        wrapper.vm.openFilterSidebar();
        await flushPromises();

        expect(wrapper.find('.sw-sidebar').classes()).toContain('is--opened');
        expect(wrapper.emitted('sw-sidebar-open')).toBeTruthy();
    });

    it('should contain a computed property, called: activeFilterNumber', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.activeFilterNumber).toBe(0);

        await wrapper.setProps({
            filterSettings: {
                Shopware: true,
                System: true,
            },
        });
        expect(wrapper.vm.activeFilterNumber).toBe(2);
    });

    it('should contain a computed property, called: isExpandedAuthorFilters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedAuthorFilters).toBe(false);

        await wrapper.setProps({
            filterSettings: {
                Shopware: true,
                System: true,
            },
            authorFilters: [
                'Shopware',
                'System',
            ],
        });
        expect(wrapper.vm.isExpandedAuthorFilters).toBe(true);
    });

    it('should contain a computed property, called: isExpandedMoreFilters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedMoreFilters).toBe(false);

        await wrapper.setProps({
            filterSettings: {
                product: true,
                order: false,
                customer: false,
            },
            filterItems: [
                'product',
                'order',
                'customer',
            ],
        });
        expect(wrapper.vm.isExpandedMoreFilters).toBe(true);
    });

    it('should be able to reset all filters', async () => {
        const wrapper = await createWrapper({
            filterSettings: {
                Shopware: true,
                System: true,
            },
        });
        await flushPromises();

        wrapper.vm.openFilterSidebar();
        await flushPromises();

        const resetAllFiltersLink = wrapper.find('.sw-snippet-settings__sidebar-reset-all');
        await resetAllFiltersLink.trigger('click');

        expect(wrapper.emitted('sidebar-reset-all')).toBeTruthy();
    });
});
