import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/component/sw-customer-card';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-customer-card'), {
        propsData: {
            customer: {},
            title: ''
        },
        stubs: {
            'sw-card': true,
            'sw-avatar': true,
            'sw-entity-single-select': true,
            'sw-text-field': true,
            'sw-page': true,
            'sw-button': true,
            'sw-language-switch': true,
            'sw-customer-address-form': true,
            'sw-customer-base-form': true,
            'sw-card-view': true,
            'sw-button-process': true,
            'sw-email-field': true,
            'sw-password-field': true,
            'sw-entity-tag-select': true,
            'sw-card-section': true,
            'sw-container': true
        }
    });
}

describe('module/sw-customer/page/sw-customer-card', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = { type: 'not', operator: 'or', queries: [{ field: 'id', type: 'equals', value: 'ed643807c9f84cc8b50132ea3ccb1c3b' }] };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });
});
