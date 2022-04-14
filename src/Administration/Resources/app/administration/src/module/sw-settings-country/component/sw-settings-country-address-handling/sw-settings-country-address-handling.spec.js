import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-address-handling';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';

function createWrapper(privileges = [], customPropsData = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-country-address-handling'), {
        localVue,

        mocks: {
            $tc: key => key,
            $route: {
                params: {
                    id: 'id'
                }
            },
            $device: {
                getSystemKey: () => {},
                onResize: () => {}
            }
        },

        propsData: {
            country: {
                isNew: () => false,
                ...customPropsData
            },
            isLoading: false
        },

        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            },
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-card': Shopware.Component.build('sw-card'),
            'sw-ignore-class': true,
            'sw-text-field': true,
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-base-field': true,
            'sw-field-error': true,
            'sw-help-text': true,
            'sw-icon': true,
            'sw-extension-component-section': true,
            'sw-code-editor': true,
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-address-handling', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to show the address handling', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ], {
            checkAdvancedPostalCodePattern: true,
        });

        await wrapper.vm.$nextTick();

        const countryForceStateInRegistrationField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );
        const countryPostalCodeRequiredField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelPostalCodeRequired"]'
        );
        const countryCheckPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckPostalCodePattern"]'
        );
        const countryCheckAdvancedPostalCodePatternFiled = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );
        const countryAdvancedPostalCodePatternField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelAdvancedPostalCodePattern"]'
        );
        const countryCheckDefaultAddressFormat = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckDefaultAddressFormat"]'
        );
        const countryAddressFormatPlain = wrapper.find(
            'sw-code-editor-stub[label="sw-settings-country.detail.labelAddressFormatPlain"]'
        );

        expect(countryForceStateInRegistrationField.attributes().disabled).toBeUndefined();
        expect(countryPostalCodeRequiredField.attributes().disabled).toBeUndefined();
        expect(countryCheckPostalCodePatternField.attributes().disabled).toBeUndefined();
        expect(countryCheckAdvancedPostalCodePatternFiled.attributes().disabled).toBeUndefined();
        expect(countryAdvancedPostalCodePatternField.attributes().disabled).toBeUndefined();
        expect(countryCheckDefaultAddressFormat.attributes().disabled).toBeUndefined();
        expect(countryAddressFormatPlain.attributes().disabled).toBeUndefined();
    });

    it('should not able to show the address handling', async () => {
        const wrapper = createWrapper([], {
            checkAdvancedPostalCodePattern: true,
        });

        await wrapper.vm.$nextTick();

        const countryForceStateInRegistrationField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );
        const countryPostalCodeRequiredField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelPostalCodeRequired"]'
        );
        const countryCheckPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckPostalCodePattern"]'
        );
        const countryCheckAdvancedPostalCodePatternFiled = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );
        const countryAdvancedPostalCodePatternField = wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelAdvancedPostalCodePattern"]'
        );
        const countryCheckDefaultAddressFormat = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckDefaultAddressFormat"]'
        );
        const countryAddressFormatPlain = wrapper.find(
            'sw-code-editor-stub[label="sw-settings-country.detail.labelAddressFormatPlain"]'
        );

        expect(countryForceStateInRegistrationField.attributes().disabled).toBeTruthy();
        expect(countryPostalCodeRequiredField.attributes().disabled).toBeTruthy();
        expect(countryCheckPostalCodePatternField.attributes().disabled).toBeTruthy();
        expect(countryCheckAdvancedPostalCodePatternFiled.attributes().disabled).toBeTruthy();
        expect(countryAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
        expect(countryCheckDefaultAddressFormat.attributes().disabled).toBeTruthy();
        expect(countryAddressFormatPlain.attributes().disabled).toBeTruthy();
    });

    it('should be able to hide postal code label', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkAdvancedPostalCodePattern: true,
            }
        });

        expect(wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelAdvancedPostalCodePattern"]'
        ).exists()).toBe(true);

        const checkAdvancedPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(3);
        await checkAdvancedPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.find(
            'sw-text-field-stub[label="sw-settings-country.detail.labelAdvancedPostalCodePattern"]'
        ).exists()).toBe(false);
    });

    it('should be able to disable postal code pattern switch field', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkAdvancedPostalCodePattern: false,
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.vm.country.checkAdvancedPostalCodePattern).toBe(false);
        expect(wrapper.vm.country.checkPostalCodePattern).toBe(true);

        const checkAdvancedPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(3);

        await checkAdvancedPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.checkPostalCodePattern).toBe(false);
    });

    it('should be able to disable advanced postal code pattern switch field', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkAdvancedPostalCodePattern: false,
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.vm.country.checkAdvancedPostalCodePattern).toBe(false);
        expect(wrapper.vm.country.checkPostalCodePattern).toBe(true);

        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(2);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.checkPostalCodePattern).toBe(false);
    });
});
