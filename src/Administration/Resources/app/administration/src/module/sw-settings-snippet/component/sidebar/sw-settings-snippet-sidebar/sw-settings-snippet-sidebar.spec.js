/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSnippetSidebar from 'src/module/sw-settings-snippet/component/sidebar/sw-settings-snippet-sidebar';

Shopware.Component.register('sw-settings-snippet-sidebar', swSettingsSnippetSidebar);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-snippet-sidebar'), {
        localVue,
        stubs: {
            'sw-sidebar': {
                template: '<div><slot></slot></div>',
            },
            'sw-sidebar-item': {
                template: '<div><slot name="headline-content"></slot><slot></slot></div>',
            },
            'sw-settings-snippet-filter-switch': true,
            'sw-sidebar-collapse': true,
        },
        propsData: {
            filterItems: [],
            authorFilters: [],
        },
    });
}

describe('sw-settings-snippet-sidebar', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain a computed property, called: activeFilterNumber', async () => {
        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.activeFilterNumber).toEqual(0);

        await wrapper.setProps({
            filterSettings: {
                Shopware: true,
                System: true,
            },
        });
        expect(wrapper.vm.activeFilterNumber).toEqual(2);

        const sidebarItem = wrapper.find('.sw-snippet-settings__sidebar > div[icon="regular-filter"]');
        expect(sidebarItem.attributes().badge).toEqual('2');
    });

    it('should contain a computed property, called: isExpandedAuthorFilters', async () => {
        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedAuthorFilters).toEqual(false);

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
        expect(wrapper.vm.isExpandedAuthorFilters).toEqual(true);
    });

    it('should contain a computed property, called: isExpandedMoreFilters', async () => {
        await wrapper.setProps({
            filterSettings: null,
        });
        expect(wrapper.vm.isExpandedMoreFilters).toEqual(false);

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
        expect(wrapper.vm.isExpandedMoreFilters).toEqual(true);
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
