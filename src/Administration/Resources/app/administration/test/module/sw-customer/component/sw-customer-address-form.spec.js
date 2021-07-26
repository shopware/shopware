import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-customer/component/sw-customer-address-form';

function createWrapper() {
    const responses = global.repositoryFactoryMock.responses;

    responses.addResponse({
        method: 'Post',
        url: '/search/country',
        status: 200,
        response: {
            data: [
                {
                    id: 'bc05040b-9da1-41ec-93ad-add9d33cd731',
                    attributes: {
                        id: '3a2e625b-f5e1-46d8-9e76-68c0e9b672a1'
                    }
                }
            ]
        }
    });

    return shallowMount(Shopware.Component.build('sw-customer-address-form'), {
        propsData: {
            customer: {},
            address: {}
        },
        stubs: {
            'sw-container': true,
            'sw-text-field': true,
            'sw-entity-single-select': true
        }
    });
}

describe('module/sw-customer/page/sw-customer-address-form', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = { type: 'not', operator: 'or', queries: [{ field: 'id', type: 'equals', value: 'ed643807c9f84cc8b50132ea3ccb1c3b' }] };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });
});
