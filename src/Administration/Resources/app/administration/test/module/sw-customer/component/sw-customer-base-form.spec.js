import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/component/sw-customer-base-form';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-customer-base-form'), {
        propsData: {
            customer: {
                id: '1',
                email: null,
                boundSalesChannelId: null,
                vatIds: [
                    '9f8f091c-db81-4ef3-862c-9c554a34cdc4'
                ]
            }
        },
        stubs: {
            'sw-container': true,
            'sw-entity-single-select': true,
            'sw-text-field': true,
            'sw-email-field': true,
            'sw-password-field': true,
            'sw-datepicker': true,
            'sw-entity-tag-select': true
        }
    });
}

describe('module/sw-customer/page/sw-customer-base-form', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = { type: 'not', operator: 'or', queries: [{ field: 'id', type: 'equals', value: 'ed643807c9f84cc8b50132ea3ccb1c3b' }] };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });
});
