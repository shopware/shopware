/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCategoryEntryPointCard from 'src/module/sw-category/component/sw-category-entry-point-card';

Shopware.Component.register('sw-category-entry-point-card', swCategoryEntryPointCard);

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

async function createWrapper(category = {}) {
    const defaultCategory = {
        navigationSalesChannels: [],
        footerSalesChannels: [],
        serviceSalesChannels: [],
    };
    const mergedCategory = {
        ...defaultCategory,
        ...category,
    };


    return shallowMount(await Shopware.Component.build('sw-category-entry-point-card'), {
        stubs: {
            'sw-card': true,
            'sw-cms-list-item': true,
            'sw-icon': true,
            'sw-single-select': true,
            'sw-category-sales-channel-multi-select': true,
            'router-link': true,
            'sw-button': true,
        },
        propsData: {
            category: mergedCategory,
        },
    });
}

describe('src/module/sw-category/component/sw-category-entry-point-card', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });


    it('should have an disabled navigation selection', async () => {
        const wrapper = await createWrapper();

        const selection = wrapper.find('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.attributes().disabled).toBe('true');
    });

    it('should have an enabled navigation selection', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const selection = wrapper.find('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.attributes().disabled).toBeUndefined();
    });

    it('should have no initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('');
    });

    it('should have main navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationSalesChannels: salesChannels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');
    });

    it('should have footer navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            footerSalesChannels: salesChannels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('footerSalesChannels');
    });

    it('should have service navigation as initial entry point', async () => {
        global.activeAclRoles = ['category.editor'];

        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            serviceSalesChannels: salesChannels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('serviceSalesChannels');
    });

    it('should reset its sales channel collections', async () => {
        global.activeAclRoles = ['category.editor'];

        const navigationSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const footerSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const serviceSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationSalesChannels,
            footerSalesChannels,
            serviceSalesChannels,
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');
        wrapper.vm.resetSalesChannelCollections();
        // it should stay on 'navigationSalesChannels' but the other collections should be cleared.
        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');

        expect(navigationSalesChannels).toHaveLength(1);
        expect(footerSalesChannels).toHaveLength(0);
        expect(serviceSalesChannels).toHaveLength(0);
    });

    it('should add newly selected sales channels', async () => {
        global.activeAclRoles = ['category.editor'];

        const navigationSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const footerSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);
        const serviceSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);


        const selectionSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: '',
                },
            },
        ]);

        const wrapper = await createWrapper({
            navigationSalesChannels,
            footerSalesChannels,
            serviceSalesChannels,
        });

        wrapper.vm.onSalesChannelChange(selectionSalesChannels);

        // the category should now have two sales channels in its 'navigationSalesChannel' collection.
        expect(wrapper.vm.category[wrapper.vm.selectedEntryPoint]).toHaveLength(2);
    });
});
