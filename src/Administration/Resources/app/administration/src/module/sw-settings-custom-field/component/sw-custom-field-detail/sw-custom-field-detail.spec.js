/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swCustomFieldDetail from 'src/module/sw-settings-custom-field/component/sw-custom-field-detail';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-modal';

Shopware.Component.register('sw-custom-field-detail', swCustomFieldDetail);

function getFieldTypes() {
    return {
        select: {
            configRenderComponent: 'sw-custom-field-type-select',
            config: {
                componentName: 'sw-single-select',
            },
        },
        checkbox: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'checkbox' },
        },
        switch: {
            configRenderComponent: 'sw-custom-field-type-checkbox',
            type: 'bool',
            config: { componentName: 'sw-field', type: 'switch' },
        },
    };
}

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-custom-field-detail'), {
        localVue,
        mocks: {
            $i18n: {
                fallbackLocale: 'en-GB',
            },
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                },
            },
            customFieldDataProviderService: {
                getTypes: () => getFieldTypes(),
            },
            SwCustomFieldListIsCustomFieldNameUnique: () => Promise.resolve(null),
            validationService: {},
            shortcutService: {
                stopEventListener: () => {},
                startEventListener: () => {},
            },
        },
        propsData: {
            currentCustomField: {
                id: 'id1',
                name: 'custom_additional_field_1',
                config: {
                    label: { 'en-GB': 'Special field 1' },
                    customFieldType: 'checkbox',
                    customFieldPosition: 1,
                },
                _isNew: true,
            },
            set: {},
        },
        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-container': true,
            'sw-custom-field-type-checkbox': true,
            'sw-field': true,
            'sw-select-field': await Shopware.Component.build('sw-select-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-icon': true,
            'sw-help-text': true,
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-loader': true,
            'sw-alert': true,
            'sw-custom-field-type-select': true,
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('can edit fields', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type select');
        const technicalNameField = wrapper.find('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSwitchField = wrapper.find('.sw-custom-field-detail__switch');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeFalsy();
        expect(technicalNameField.attributes('disabled')).toBeFalsy();
        expect(modalPositionField.attributes('disabled')).toBeFalsy();
        expect(modalSwitchField.attributes('disabled')).toBeFalsy();
        expect(modalSaveButton.attributes('disabled')).toBeFalsy();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type select');
        const technicalNameField = wrapper.find('.sw-custom-field-detail__technical-name');
        const modalPositionField = wrapper.find('.sw-custom-field-detail__modal-position');
        const modalSwitchField = wrapper.find('.sw-custom-field-detail__switch');
        const modalSaveButton = wrapper.find('.sw-custom-field-detail__footer-save');

        expect(modalTypeField.attributes('disabled')).toBeTruthy();
        expect(technicalNameField.attributes('disabled')).toBeTruthy();
        expect(modalPositionField.attributes('disabled')).toBeTruthy();
        expect(modalSwitchField.attributes('disabled')).toBeTruthy();
        expect(modalSaveButton.attributes('disabled')).toBeTruthy();
    });

    it('should update config correctly', async () => {
        const wrapper = await createWrapper(['custom_field.editor']);

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type select');
        await modalTypeField.setValue('select');

        expect(wrapper.vm.currentCustomField.config).toEqual(expect.objectContaining({
            customFieldType: 'select',
        }));

        await modalTypeField.setValue('switch');

        expect(wrapper.vm.currentCustomField.config).toEqual(expect.objectContaining({
            customFieldType: 'switch',
        }));

        const saveButton = wrapper.find('.sw-custom-field-detail__footer-save');
        await saveButton.trigger('click');

        expect(wrapper.vm.currentCustomField.config).toEqual(expect.objectContaining({
            customFieldType: 'switch',
            componentName: 'sw-field',
        }));
    });
});
