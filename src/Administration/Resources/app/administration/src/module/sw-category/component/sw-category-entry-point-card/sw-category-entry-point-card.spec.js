/**
 * @package content
 */
import { mount } from '@vue/test-utils';

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

    return mount(await wrapTestComponent('sw-category-entry-point-card', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-cms-list-item': true,
                'sw-icon': true,
                'sw-single-select': {
                    template: '<div class="sw-single-select"></div>',
                    props: ['disabled'],
                },
                'sw-category-sales-channel-multi-select': true,
                'router-link': true,
                'sw-button': true,
            },
        },
        props: {
            category: mergedCategory,
        },
    });
}

describe('src/module/sw-category/component/sw-category-entry-point-card', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should have an disabled navigation selection', async () => {
        const wrapper = await createWrapper();

        const selection = wrapper.getComponent('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.props('disabled')).toBe(true);
    });

    it('should have an enabled navigation selection', async () => {
        global.activeAclRoles = ['category.editor'];

        const wrapper = await createWrapper();

        const selection = wrapper.getComponent('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.props('disabled')).toBe(false);
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
