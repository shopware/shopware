/**
 * @sw-package fundamentals@discovery
 */
import { mount } from '@vue/test-utils';

let openContentMock;

async function createWrapper() {
    openContentMock = jest.fn();

    return mount(
        await wrapTestComponent('sw-settings-snippet-sidebar', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-sidebar': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-sidebar-item': {
                        template: '<div><slot name="headline-content"></slot><slot></slot></div>',
                        methods: {
                            openContent: openContentMock,
                        },
                    },
                    'sw-settings-snippet-filter-switch': true,
                    'sw-sidebar-collapse': true,
                },
            },
            props: {
                filterItems: [],
                authorFilters: [],
            },
        },
    );
}

describe('sw-settings-snippet-sidebar', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should register the open filters shortcut', async () => {
        expect(wrapper.vm.$options.shortcuts.OF).toBe('openFilterSidebar');
    });

    it('should open the filter sidebar', async () => {
        wrapper.vm.openFilterSidebar();

        expect(openContentMock).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('sw-sidebar-open')).toHaveLength(1);
    });

    it('should contain a computed property, called: activeFilterNumber', async () => {
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

        const sidebarItem = wrapper.find('.sw-snippet-settings__sidebar > div[icon="regular-filter"]');
        expect(sidebarItem.attributes().badge).toBe('2');
    });

    it('should contain a computed property, called: isExpandedAuthorFilters', async () => {
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
        await wrapper.setProps({
            filterSettings: {
                Shopware: true,
                System: true,
            },
        });

        const resetAllFiltersLink = wrapper.find('.sw-snippet-settings__sidebar-reset-all');
        await resetAllFiltersLink.trigger('click');

        expect(wrapper.emitted('sidebar-reset-all')).toBeTruthy();
    });
});
