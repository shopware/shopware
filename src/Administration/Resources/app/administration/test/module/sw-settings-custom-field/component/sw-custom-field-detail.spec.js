import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-custom-field/component/sw-custom-field-detail';

function getFieldTypes() {
    return {
        checkbox: {
            config: {
                componentName: 'sw-field',
                type: 'checkbox'
            },
            configRenderComponent: 'sw-custom-field-type-checkbox'
        }
    };
}

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-custom-field-detail'), {
        localVue,
        mocks: {
            $i18n: {
                fallbackLocale: 'en-GB'
            }
        },
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            customFieldDataProviderService: {
                getTypes: () => getFieldTypes()
            },
            SwCustomFieldListIsCustomFieldNameUnique: {}
        },
        propsData: {
            currentCustomField: {
                id: 'id1',
                name: 'custom_additional_field_1',
                config: {
                    label: { 'en-GB': 'Special field 1' },
                    customFieldType: 'checkbox',
                    customFieldPosition: 1
                },
                _isNew: true
            },
            set: {}
        },
        stubs: {
            'sw-modal': true,
            'sw-container': true,
            'sw-custom-field-type-checkbox': true,
            'sw-field': true,
            'sw-button': true,
            'sw-loader': true,
            'sw-alert': true
        }
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-detail', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('can edit fields', async () => {
        const wrapper = createWrapper([
            'custom_field.editor'
        ]);

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type');
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
        const wrapper = createWrapper();

        const modalTypeField = wrapper.find('.sw-custom-field-detail__modal-type');
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
});
