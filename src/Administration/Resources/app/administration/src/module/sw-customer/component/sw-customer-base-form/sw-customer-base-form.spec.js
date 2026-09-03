import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const customer = {
    id: '1',
    email: null,
    boundSalesChannelId: null,
    vatIds: [
        '9f8f091c-db81-4ef3-862c-9c554a34cdc4',
    ],
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-customer-base-form', { sync: true }), {
        props: {
            customer,
        },
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-email-field': true,
                'sw-datepicker': true,
                'sw-entity-tag-select': true,
                'sw-single-select': true,
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-base-form', () => {
    it('should exclude the default salutation from selectable salutations', async () => {
        const wrapper = await createWrapper();
        const criteria = wrapper.vm.salutationCriteria;
        const expectedCriteria = {
            type: 'not',
            operator: 'or',
            queries: [
                {
                    field: 'id',
                    type: 'equals',
                    value: 'ed643807c9f84cc8b50132ea3ccb1c3b',
                },
            ],
        };

        expect(criteria.filters).toContainEqual(expectedCriteria);
    });

    it('should display the account type switcher', async () => {
        const wrapper = await createWrapper();
        const accountTypeSelect = wrapper.find('.sw-customer-base-form__account-type-select');
        expect(accountTypeSelect.exists()).toBeTruthy();
    });

    it('should display the language field', async () => {
        const wrapper = await createWrapper();
        const languageSelect = wrapper.find('.sw-customer-base-form__language-select');
        expect(languageSelect.exists()).toBeTruthy();
    });

    it('should filter the selectable languages by the selected sales channel', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            customer: {
                ...customer,
                salesChannelId: 'salesChannelId1',
            },
        });

        const criteria = wrapper.vm.languageCriteria;

        expect(criteria.filters).toContainEqual({
            type: 'equals',
            field: 'salesChannels.id',
            value: 'salesChannelId1',
        });
    });

    it('should not filter the selectable languages when no sales channel is selected', async () => {
        const wrapper = await createWrapper();

        const criteria = wrapper.vm.languageCriteria;

        expect(criteria.filters).toHaveLength(0);
    });
});
