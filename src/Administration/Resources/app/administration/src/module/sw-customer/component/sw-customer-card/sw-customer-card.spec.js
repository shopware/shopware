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

async function createWrapper({ props = {}, systemConfig } = {}) {
    return mount(await wrapTestComponent('sw-customer-card', { sync: true }), {
        props: {
            customer: {},
            title: '',
            ...props,
        },
        global: {
            provide: {
                contextStoreService: {},
                ...(systemConfig === undefined
                    ? {}
                    : { systemConfigApiService: { getValues: () => Promise.resolve(systemConfig) } }),
            },
            stubs: {
                'sw-avatar': true,
                'sw-entity-single-select': true,
                'sw-text-field': true,
                'sw-page': true,
                'sw-language-switch': true,
                'sw-customer-address-form': true,
                'sw-customer-base-form': true,
                'sw-card-view': true,
                'sw-button-process': true,
                'sw-email-field': true,
                'sw-entity-tag-select': true,
                'sw-card-section': await wrapTestComponent('sw-card-section'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-single-select': true,
                'sw-customer-imitate-customer-modal': true,
                'sw-label': true,
                'sw-extension-component-section': true,
                'sw-ai-copilot-badge': true,
                'sw-context-button': true,
                'sw-loader': true,
            },
        },
    });
}

describe('module/sw-customer/page/sw-customer-card', () => {
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
        expect(wrapper.find('[aria-label="sw-customer.card.labelCompany"]').exists()).toBeTruthy();
        expect(wrapper.find('[aria-label="sw-customer.card.labelVatId"]').exists()).toBeTruthy();
    });

    it('should keep the raw name fields for the avatar initials', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...customer,
                accountType: 'business',
                firstName: 'Ada',
                lastName: 'van Halen',
                company: 'Acme GmbH',
            },
        });

        expect(wrapper.vm.avatarName).toEqual({ firstName: 'Ada', lastName: 'van Halen' });
    });

    it('should fall back to the company for the avatar of a nameless company account', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...customer,
                accountType: 'business',
                firstName: '',
                lastName: '',
                company: 'Acme GmbH',
            },
        });

        expect(wrapper.vm.avatarName).toEqual({ firstName: 'Acme', lastName: 'GmbH' });
    });

    it('should keep the contact person in the card title', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...customer,
                accountType: 'business',
                firstName: 'Ada',
                lastName: 'Lovelace',
                company: 'Acme GmbH',
            },
        });

        expect(wrapper.vm.fullName).toBe('Ada Lovelace - Acme GmbH');
    });

    it('should use the company as the card title without a contact person', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...customer,
                accountType: 'business',
                firstName: '',
                lastName: '',
                company: 'Acme GmbH',
            },
        });

        expect(wrapper.vm.fullName).toBe('Acme GmbH');
    });

    it('should not pair the salutation with the company when there is no contact person', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            customer: {
                ...customer,
                accountType: 'business',
                firstName: '',
                lastName: '',
                company: 'Acme GmbH',
                salutation: { translated: { displayName: 'Mr' } },
            },
        });

        expect(wrapper.vm.fullName).toBe('Acme GmbH');
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

    it('should keep the contact person required for a private account', async () => {
        const wrapper = await createWrapper({
            props: { customer: { accountType: 'private' } },
            systemConfig: {
                'core.loginRegistration.showAccountTypeSelection': true,
                'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
                'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
            },
        });
        await flushPromises();

        expect(wrapper.vm.contactPersonRequired).toBe(true);
    });

    it('should make the contact person optional for a company account when the settings allow it', async () => {
        const wrapper = await createWrapper({
            props: { customer: { accountType: 'business' } },
            systemConfig: {
                'core.loginRegistration.showAccountTypeSelection': true,
                'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
                'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
            },
        });
        await flushPromises();

        expect(wrapper.vm.contactPersonRequired).toBe(false);
    });

    it('should keep the contact person required for a company account without the account type selection', async () => {
        const wrapper = await createWrapper({
            props: { customer: { accountType: 'business' } },
            systemConfig: {
                'core.loginRegistration.showAccountTypeSelection': false,
                'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
                'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
            },
        });
        await flushPromises();

        expect(wrapper.vm.contactPersonRequired).toBe(true);
    });

    it('should keep the contact person required when no config service is provided', async () => {
        const wrapper = await createWrapper({ props: { customer: { accountType: 'business' } } });
        await flushPromises();

        expect(wrapper.vm.contactPersonRequired).toBe(true);
    });
});
