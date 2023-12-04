/**
 * @package services-settings
 */
import { shallowMount } from '@vue/test-utils_v2';
import swImportExportEditProfileGeneral from 'src/module/sw-import-export/component/sw-import-export-edit-profile-general';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/sw-text-field';

Shopware.Component.register('sw-import-export-edit-profile-general', swImportExportEditProfileGeneral);

describe('module/sw-import-export/components/sw-import-export-edit-profile-general', () => {
    /** @type Wrapper */
    let wrapper;

    function getProfileMock() {
        return {
            type: 'import-export',
            sourceEntity: 'product',
            label: 'Product profile',
            systemDefault: false,
            mappings: [
                {
                    key: 'id',
                    mappedKey: 'id',
                },
            ],
            translated: {
                label: 'Product profile',
            },
        };
    }

    async function createWrapper(profile) {
        return shallowMount(await Shopware.Component.build('sw-import-export-edit-profile-general'), {
            propsData: {
                profile,
            },
            stubs: {
                'sw-container': {
                    template: '<div><slot></slot></div>',
                },
                'sw-field': await Shopware.Component.build('sw-field'),
                'sw-text-field': await Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-field-error': true,
                'sw-single-select': await Shopware.Component.build('sw-single-select'),
                'sw-select-result': await Shopware.Component.build('sw-select-result'),
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
                'sw-select-base': await Shopware.Component.build('sw-select-base'),
                'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
                'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
                'sw-icon': true,
            },
            provide: {
                validationService: {},
            },
        });
    }

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper(getProfileMock());
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have disabled fields', async () => {
        const profile = getProfileMock();
        profile.systemDefault = true;

        wrapper = await createWrapper(profile);

        const nameField = wrapper.find('input[type="text"]');
        expect(nameField.attributes('disabled')).toBe('disabled');

        const typeSelect = wrapper.find('.sw-import-export-edit-profile-general__type-select');
        expect(typeSelect.attributes('disabled')).toBe('disabled');

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__object-type-select');
        expect(objectSelect.attributes('disabled')).toBe('disabled');
    });

    it.each([
        'import-export',
        'import',
    ])('should disable export forbidden entity when type is %s', async (type) => {
        const profile = getProfileMock();
        profile.type = type;

        wrapper = await createWrapper(profile);

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__object-type-select .sw-single-select__selection');
        await objectSelect.trigger('click');

        const orderOption = wrapper.find('.sw-select-option--order');
        expect(orderOption.classes()).toContain('is--disabled');

        const productOption = wrapper.find('.sw-select-option--product');
        expect(productOption.classes()).not.toContain('is--disabled');
    });

    it('should disable import-export and import option when entity is export only', async () => {
        const profile = getProfileMock();
        profile.sourceEntity = 'order';

        wrapper = await createWrapper(profile);

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__type-select .sw-single-select__selection');
        await objectSelect.trigger('click');

        const importOption = wrapper.find('.sw-select-option--import');
        expect(importOption.classes()).toContain('is--disabled');

        const importExportOption = wrapper.find('.sw-select-option--import-export');
        expect(importExportOption.classes()).toContain('is--disabled');

        const exportOption = wrapper.find('.sw-select-option--export');
        expect(exportOption.classes()).not.toContain('is--disabled');
    });
});
