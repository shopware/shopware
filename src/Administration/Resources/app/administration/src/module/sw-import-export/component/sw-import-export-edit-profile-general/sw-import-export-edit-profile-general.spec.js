/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

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
    return mount(await wrapTestComponent('sw-import-export-edit-profile-general', { sync: true }), {
        global: {
            stubs: {
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-icon': true,
                'sw-field-copyable': true,
                'sw-loader': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                validationService: {},
            },
        },
        props: {
            profile,
        },
    });
}

describe('module/sw-import-export/components/sw-import-export-edit-profile-general', () => {
    let wrapper;

    it('should have disabled fields', async () => {
        const profile = getProfileMock();
        profile.systemDefault = true;

        wrapper = await createWrapper(profile);
        await flushPromises();

        const nameField = wrapper.find('input[type="text"]');
        expect(nameField.attributes('disabled')).toBe('');

        const typeSelect = wrapper.find('.sw-import-export-edit-profile-general__type-select');
        expect(typeSelect.classes()).toContain('is--disabled');

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__object-type-select');
        expect(objectSelect.classes()).toContain('is--disabled');
    });

    it.each([
        'import-export',
        'import',
    ])('should disable export forbidden entity when type is %s', async (type) => {
        const profile = getProfileMock();
        profile.type = type;

        wrapper = await createWrapper(profile);
        await flushPromises();

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__object-type-select .sw-single-select__selection');
        await objectSelect.trigger('click');
        await flushPromises();

        const orderOption = wrapper.find('.sw-select-option--order');
        expect(orderOption.classes()).toContain('is--disabled');

        const productOption = wrapper.find('.sw-select-option--product');
        expect(productOption.classes()).not.toContain('is--disabled');
    });

    it('should disable import-export and import option when entity is export only', async () => {
        const profile = getProfileMock();
        profile.sourceEntity = 'order';

        wrapper = await createWrapper(profile);
        await flushPromises();

        const objectSelect = wrapper.find('.sw-import-export-edit-profile-general__type-select .sw-single-select__selection');
        await objectSelect.trigger('click');
        await flushPromises();

        const importOption = wrapper.find('.sw-select-option--import');
        expect(importOption.classes()).toContain('is--disabled');

        const importExportOption = wrapper.find('.sw-select-option--import-export');
        expect(importExportOption.classes()).toContain('is--disabled');

        const exportOption = wrapper.find('.sw-select-option--export');
        expect(exportOption.classes()).not.toContain('is--disabled');
    });
});
