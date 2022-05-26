import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-address-handling';
import 'src/app/component/base/sw-card';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import { FORMAT_ADDRESS_TEMPLATE } from 'src/module/sw-settings-country/constant/address.constant';

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
            userInputSanitizeService: {},
            feature: {
                isActive: () => true
            }
        },

        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot></slot></div>'
            },
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

enableAutoDestroy(afterEach);

describe('module/sw-settings-country/component/sw-settings-country-address-handling', () => {
    beforeAll(() => {
        Shopware.State.get('session').currentUser = {};
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to edit the address handling tab', async () => {
        const wrapper = await createWrapper([
            'country.editor'
        ],);

        const countryForceStateInRegistrationField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelForceStateInRegistration"]'
        );

        const countryPostalCodeRequiredField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelPostalCodeRequired"]'
        );

        const countryCheckPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckPostalCodePattern"]'
        );

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
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
        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
        expect(countryCheckDefaultAddressFormat.attributes().disabled).toBeUndefined();
        expect(countryAddressFormatPlain.attributes().disabled).toBeUndefined();
    });

    it('should not able to edit the address handling tab', async () => {
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

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
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
        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
        expect(countryCheckDefaultAddressFormat.attributes().disabled).toBeTruthy();
        expect(countryAddressFormatPlain.attributes().disabled).toBeTruthy();
    });

    it('should be able to toggle advanced postal code pattern', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.find('.advanced-postal-code .is--disabled').exists()).toBeTruthy();

        const checkAdvancedPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(3);
        await checkAdvancedPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.find(
            '.advanced-postal-code .is--disabled'
        ).exists()).toBeFalsy();
    });

    it('should be not able to toggle advanced postal code pattern', async () => {
        const wrapper = createWrapper([
            'country.editor'
        ]);

        await wrapper.setProps({
            country: {
                checkAdvancedPostalCodePattern: true,
                checkPostalCodePattern: true,
            }
        });

        expect(wrapper.find(
            '.advanced-postal-code .is--disabled'
        ).exists()).toBeFalsy();

        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(2);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.find('.advanced-postal-code .is--disabled').exists()).toBeTruthy();

        const countryCheckAdvancedPostalCodePatternField = wrapper.find(
            'sw-base-field-stub[label="sw-settings-country.detail.labelCheckAdvancedPostalCodePattern"]'
        );

        expect(countryCheckAdvancedPostalCodePatternField.attributes().disabled).toBeTruthy();
    });

    it('should be able to revert address format to default', async () => {
        const wrapper = createWrapper(['country.editor']);

        await wrapper.setProps({
            country: {
                ...wrapper.vm.country,
                useDefaultAddressFormat: true
            }
        });

        expect(wrapper.vm.country.advancedAddressFormatPlain).toEqual(FORMAT_ADDRESS_TEMPLATE);

        const useDefaultAddressFormatField = wrapper.findAll('.sw-field--switch').at(4);
        await useDefaultAddressFormatField
            .find('.sw-field--switch__input input')
            .trigger('click');

        await wrapper.find('sw-code-editor-stub')
            .vm
            .$emit('input', '{{ company }} - {{ department }}');

        expect(wrapper.vm.country.advancedAddressFormatPlain).toEqual('{{ company }} - {{ department }}');

        await useDefaultAddressFormatField
            .find('.sw-field--switch__input input')
            .trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.country.advancedAddressFormatPlain).toEqual(FORMAT_ADDRESS_TEMPLATE);
    });

    it('should revert advanced postal code pattern when toggle on Advanced validation rules', async () => {
        const wrapper = createWrapper(['country.editor']);

        await wrapper.setProps({
            country: {
                checkPostalCodePattern: true,
                checkAdvancedPostalCodePattern: true,
                advancedPostalCodePattern: '/^\\d{5}(?:[- ]?\\d{4})?$/',
            }
        });


        const checkPostalCodePatternField = wrapper.findAll('.sw-field--switch').at(2);

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.advancedPostalCodePattern).toBeNull();

        await checkPostalCodePatternField
            .find('.sw-field--switch__input input')
            .trigger('click');

        const checkAdvancedPostalCodePattern = wrapper.findAll('.sw-field--switch').at(3);

        await checkAdvancedPostalCodePattern
            .find('.sw-field--switch__input input')
            .trigger('click');

        expect(wrapper.vm.country.advancedPostalCodePattern).toEqual('/^\\d{5}(?:[- ]?\\d{4})?$/');
    });
});
