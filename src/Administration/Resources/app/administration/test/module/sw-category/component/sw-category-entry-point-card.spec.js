import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-category/component/sw-category-entry-point-card';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

function createWrapper(privileges = [], category = {}) {
    const localVue = createLocalVue();
    const defaultCategory = {
        navigationSalesChannels: [],
        footerSalesChannels: [],
        serviceSalesChannels: []
    };
    const mergedCategory = {
        ...defaultCategory,
        ...category
    };


    return shallowMount(Shopware.Component.build('sw-category-entry-point-card'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-cms-list-item': true,
            'sw-icon': true,
            'sw-single-select': true,
            'sw-category-sales-channel-multi-select': true,
            'router-link': true,
            'sw-button': true
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            category: mergedCategory
        }
    });
}

describe('src/module/sw-category/component/sw-category-entry-point-card', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });


    it('should have an disabled navigation selection', async () => {
        const wrapper = createWrapper();

        const selection = wrapper.find('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.attributes().disabled).toBe('true');
    });

    it('should have an enabled navigation selection', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        const selection = wrapper.find('.sw-category-entry-point-card__entry-point-selection');

        expect(selection.attributes().disabled).toBeUndefined();
    });

    it('should have no initial entry point', async () => {
        const wrapper = createWrapper([
            'category.editor'
        ]);

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('');
    });

    it('should have main navigation as initial entry point', async () => {
        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);

        const wrapper = createWrapper([
            'category.editor'
        ], {
            navigationSalesChannels: salesChannels
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');
    });

    it('should have footer navigation as initial entry point', async () => {
        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);

        const wrapper = createWrapper([
            'category.editor'
        ], {
            footerSalesChannels: salesChannels
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('footerSalesChannels');
    });

    it('should have service navigation as initial entry point', async () => {
        const salesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);

        const wrapper = createWrapper([
            'category.editor'
        ], {
            serviceSalesChannels: salesChannels
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('serviceSalesChannels');
    });

    it('should reset its sales channel collections', async () => {
        const navigationSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);
        const footerSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);
        const serviceSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);

        const wrapper = createWrapper([
            'category.editor'
        ], {
            navigationSalesChannels,
            footerSalesChannels,
            serviceSalesChannels
        });

        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');
        wrapper.vm.resetSalesChannelCollections();
        // it should stay on 'navigationSalesChannels' but the other collections should be cleared.
        expect(wrapper.vm.getInitialEntryPointFromCategory()).toBe('navigationSalesChannels');

        expect(navigationSalesChannels.length).toBe(1);
        expect(footerSalesChannels.length).toBe(0);
        expect(serviceSalesChannels.length).toBe(0);
    });

    it('should add newly selected sales channels', async () => {
        const navigationSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);
        const footerSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);
        const serviceSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);


        const selectionSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api, null, [
            {
                id: '',
                name: '',
                translated: {
                    name: ''
                }
            }
        ]);

        const wrapper = createWrapper([
            'category.editor'
        ], {
            navigationSalesChannels,
            footerSalesChannels,
            serviceSalesChannels
        });

        wrapper.vm.onSalesChannelChange(selectionSalesChannels);

        // the category should now have two sales channels in its 'navigationSalesChannel' collection.
        expect(wrapper.vm.category[wrapper.vm.selectedEntryPoint].length).toBe(2);
    });
});
