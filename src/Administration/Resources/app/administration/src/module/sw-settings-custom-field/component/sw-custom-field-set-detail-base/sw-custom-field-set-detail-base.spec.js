/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swCustomFieldSetDetailBase from 'src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base';

Shopware.Component.register('sw-custom-field-set-detail-base', swCustomFieldSetDetailBase);

function getFieldTypes() {
    return {
        checkbox: {
            config: {
                componentName: 'sw-field',
                type: 'checkbox',
            },
            configRenderComponent: 'sw-custom-field-type-checkbox',
        },
    };
}

async function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(await Shopware.Component.build('sw-custom-field-set-detail-base'), {
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
        },
        propsData: {
            set: {
                _isNew: true,
            },
        },
        stubs: {
            'sw-card': true,
            'sw-container': true,
            'sw-custom-field-type-checkbox': true,
            'sw-number-field': true,
            'sw-text-field': true,
            'sw-button': true,
            'sw-multi-select': true,
            'sw-loader': true,
        },
    });
}

describe('src/module/sw-settings-custom-field/component/sw-custom-field-set-detail-base', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('can edit fields', async () => {
        const wrapper = await createWrapper([
            'custom_field.editor',
        ]);

        const technicalNameField = wrapper.find('.sw-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.find('.sw-settings-custom-field-set-detail-base__base-postion');
        const entitiesField = wrapper.find('.sw-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.attributes('disabled')).toBeFalsy();
        expect(positionField.attributes('disabled')).toBeFalsy();
        expect(entitiesField.attributes('disabled')).toBeFalsy();
    });

    it('cannot edit fields', async () => {
        const wrapper = await createWrapper();

        const technicalNameField = wrapper.find('.sw-settings-custom-field-set-detail-base__technical-name');
        const positionField = wrapper.find('.sw-settings-custom-field-set-detail-base__base-postion');
        const entitiesField = wrapper.find('.sw-settings-custom-field-set-detail-base__label-entities');

        expect(technicalNameField.attributes('disabled')).toBeTruthy();
        expect(positionField.attributes('disabled')).toBeTruthy();
        expect(entitiesField.attributes('disabled')).toBeTruthy();
    });
});

