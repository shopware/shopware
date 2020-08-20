import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-customer-group/page/sw-settings-customer-group-list';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-customer-group-list'), {
        localVue,
        mocks: {
            $tc: (translationPath) => translationPath,
            $router: { replace: () => {} },
            $route: { query: '' }
        },
        stubs: {
            'sw-page': '<div><slot name="content"></slot></div>',
            'sw-card-view': '<div><slot></slot></div>',
            'sw-card': '<div><slot></slot></div>'
        },
        provide: {
            repositoryFactory: {
                create: () => ({ search: () => Promise.resolve() })
            }
        }
    });
}

// These two functions contain the bare minimum for the unit test to complete
function createCustomerGroupWithCustomer() {
    return {
        customers: [
            {}
        ],
        salesChannels: []
    };
}

function createDeletableCustomerGroup() {
    return {
        customers: [],
        salesChannels: []
    };
}

describe('src/module/sw-settings-customer-group/page/sw-settings-customer-group-list', () => {
    it('should be a vue js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should return false if customer group has a customer and/or SalesChannel assigned to it', () => {
        const wrapper = createWrapper();
        const customerGroup = createCustomerGroupWithCustomer();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(false);
    });

    it('should return true if customer group has no customer and no SalesChannel assigned to id', () => {
        const wrapper = createWrapper();
        const customerGroup = createDeletableCustomerGroup();

        expect(wrapper.vm.customerGroupCanBeDeleted(customerGroup)).toBe(true);
    });
});
