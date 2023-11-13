import { shallowMount } from '@vue/test-utils';
import swCustomerCard from 'src/module/sw-customer/component/sw-customer-card';

/**
 * @package checkout
 */

Shopware.Component.register('sw-customer-card', swCustomerCard);

const customer = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
    vatIds: [
        '9f8f091c-db81-4ef3-862c-9c554a34cdc4',
    ],
};

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-customer-card'), {
        propsData: {
            customer: {},
            title: '',
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
            'sw-container': true,
            'sw-single-select': true,
        },
    });
}

describe('module/sw-customer/page/sw-customer-card', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = { type: 'not', operator: 'or', queries: [{ field: 'id', type: 'equals', value: 'ed643807c9f84cc8b50132ea3ccb1c3b' }] };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should display the account type switcher', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
        });
        const accountTypeSelect = wrapper.find('.sw-customer-card__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should vat fields when switching to business type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'business',
            },
        });
        expect(wrapper.find('[label="sw-customer.card.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[label="sw-customer.card.labelVatId"]').exists()).toBeTruthy();
    });

    it('should hide vat fields when switching to private type', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            editMode: true,
            customer: {
                ...customer,
                accountType: 'private',
            },
        });

        expect(wrapper.find('[label="sw-customer.card.labelVatId"]').exists()).toBeFalsy();
    });
});
