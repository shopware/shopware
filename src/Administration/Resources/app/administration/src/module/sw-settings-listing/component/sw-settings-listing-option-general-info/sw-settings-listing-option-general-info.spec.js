import { shallowMount } from '@vue/test-utils';
import swSettingsListingOptionGeneralInfo from 'src/module/sw-settings-listing/component/sw-settings-listing-option-general-info';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-field-error';

Shopware.Component.register('sw-settings-listing-option-general-info', swSettingsListingOptionGeneralInfo);

describe('src/module/sw-settings-listing/component/sw-settings-listing-option-general-info', () => {
    async function createWrapper() {
        return shallowMount(await Shopware.Component.build('sw-settings-listing-option-general-info'), {
            provide: {
                validationService: {},
            },
            directives: {
                tooltip() {},
            },
            propsData: {
                sortingOption: {
                    label: 'Price descending',
                },
                isDefaultSorting: false,
            },
            stubs: {
                'sw-card': {
                    template: '<div><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
                'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-field-error': await Shopware.Component.build('sw-field-error'),
            },
        });
    }

    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('is a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should display the correct name', async () => {
        const textField = wrapper.find('.sw-field--text input');

        expect(textField.element.value).toBe('Price descending');
    });

    it('should display the correct active state', async () => {
        const switchField = wrapper.find('.sw-field--switch input');
        const isActive = switchField.element.value;

        expect(isActive).toBe('on');
    });

    it('should not disable active state switch on normal product sortings', async () => {
        const switchField = wrapper.find('.sw-field--switch input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBeUndefined();
    });

    it('should disable active state switch on default sortings', async () => {
        await wrapper.setProps({ isDefaultSorting: true });

        const switchField = wrapper.find('.sw-field--switch input');
        const isDisabled = switchField.attributes('disabled');

        expect(isDisabled).toBe('disabled');
    });
});
