/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], customPropsData = {}) {
    return mount(await wrapTestComponent('sw-settings-country-general', {
        sync: true,
    }), {
        props: {
            country: {
                isNew: () => false,
                customerTax: {
                    enabled: customPropsData.enabled,
                },
                companyTax: {
                    enabled: customPropsData.enabled,
                },
                ...customPropsData,
            },
            userConfig: {},
            userConfigValues: {},
            isLoading: false,
        },

        global: {
            mocks: {
                $tc: key => key,
                $route: {
                    params: {
                        id: 'id',
                    },
                },
                $device: {
                    getSystemKey: () => {},
                    onResize: () => {},
                },
            },

            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () => {
                            return Promise.resolve({});
                        },
                        search: () => {
                            return Promise.resolve({
                                userConfigs: {
                                    first: () => ({}),
                                },
                            });
                        },
                    }),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) { return true; }

                        return privileges.includes(identifier);
                    },
                },
                feature: {
                    isActive: () => true,
                },
            },

            stubs: {
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-ignore-class': true,
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-icon': true,
                'sw-number-field': true,
                'sw-settings-country-currency-dependent-modal': true,
                'sw-entity-single-select': true,
                'sw-extension-component-section': true,
            },
        },
    });
}

describe('module/sw-settings-country/component/sw-settings-country-general', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to show the tax free from', async () => {
        const wrapper = await createWrapper([
            'country.editor',
        ], {
            enabled: true,
        });

        await wrapper.vm.$nextTick();

        const countryNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelName"]',
        );
        const countryPositionField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-country.detail.labelPosition"]',
        );
        const countryIsoField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelIso"]',
        );
        const countryIso3Field = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelIso3"]',
        );
        const countryActiveField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelActive"]',
        );
        const countryShippingAvailableField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelShippingAvailable"]',
        );
        const countryTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelTaxFree"]',
        );
        const countryCompaniesTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCompanyTaxFree"]',
        );
        const countryCheckVatIdFormatField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCheckVatIdFormat"]',
        );
        const countryTaxFreeFromField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-country.detail.taxFreeFrom"]',
        );
        const countryVatIdRequiredField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelVatIdRequired"]',
        );

        const countryIsEuField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelIsEu"]',
        );

        expect(countryNameField.attributes().disabled).toBeUndefined();
        expect(countryPositionField.attributes().disabled).toBeUndefined();
        expect(countryIsoField.attributes().disabled).toBeUndefined();
        expect(countryIso3Field.attributes().disabled).toBeUndefined();
        expect(countryActiveField.attributes().disabled).toBeUndefined();
        expect(countryShippingAvailableField.attributes().disabled).toBeUndefined();
        expect(countryTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeUndefined();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeUndefined();
        expect(countryTaxFreeFromField.attributes()).toBeDefined();
        expect(countryVatIdRequiredField.attributes().disabled).toBeUndefined();
        expect(countryIsEuField.attributes().disabled).toBeUndefined();
    });

    it('should not able to show the tax free from', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const countryNameField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelName"]',
        );
        const countryPositionField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-country.detail.labelPosition"]',
        );
        const countryIsoField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelIso"]',
        );
        const countryIso3Field = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelIso3"]',
        );
        const countryActiveField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelActive"]',
        );
        const countryShippingAvailableField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelShippingAvailable"]',
        );
        const countryTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelTaxFree"]',
        );
        const countryCompaniesTaxFreeField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCompanyTaxFree"]',
        );
        const countryCheckVatIdFormatField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelCheckVatIdFormat"]',
        );
        const countryTaxFreeFromField = wrapper.find(
            'sw-number-field-stub[label="sw-settings-country.detail.taxFreeFrom"]',
        );
        const currencyDropdownList = wrapper.find(
            'sw-entity-single-select-stub',
        );
        const countryVatIdRequiredField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelVatIdRequired"]',
        );

        const countryIsEuField = wrapper.find(
            'sw-switch-field-stub[label="sw-settings-country.detail.labelIsEu"]',
        );

        expect(countryNameField.attributes().disabled).toBeTruthy();
        expect(countryPositionField.attributes().disabled).toBeTruthy();
        expect(countryIsoField.attributes().disabled).toBeTruthy();
        expect(countryIso3Field.attributes().disabled).toBeTruthy();
        expect(countryActiveField.attributes().disabled).toBeTruthy();
        expect(countryShippingAvailableField.attributes().disabled).toBeTruthy();
        expect(countryTaxFreeField.attributes().disabled).toBeTruthy();
        expect(countryCompaniesTaxFreeField.attributes().disabled).toBeTruthy();
        expect(countryCheckVatIdFormatField.attributes().disabled).toBeTruthy();
        expect(countryTaxFreeFromField.exists()).toBe(false);
        expect(currencyDropdownList.exists()).toBe(false);
        expect(countryVatIdRequiredField.attributes().disabled).toBeTruthy();
        expect(countryIsEuField.attributes().disabled).toBeTruthy();
    });
});
