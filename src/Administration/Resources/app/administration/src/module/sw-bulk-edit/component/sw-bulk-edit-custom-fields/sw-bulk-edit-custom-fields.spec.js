/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

async function createWrapper(customProps = {}) {
    return mount(await wrapTestComponent('sw-bulk-edit-custom-fields', { sync: true }), {
        global: {
            stubs: {
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-number-field': await wrapTestComponent('sw-number-field'),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-entity-multi-select': true,
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': true,
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-media-field': await wrapTestComponent('sw-media-field'),
                'sw-media-media-item': await wrapTestComponent('sw-media-media-item'),
                'sw-media-base-item': await wrapTestComponent('sw-media-base-item'),
                'sw-media-preview-v2': await wrapTestComponent('sw-media-preview-v2'),
                'sw-colorpicker': await wrapTestComponent('sw-text-field'),
                'sw-upload-listener': true,
                'sw-simple-search-field': true,
                'sw-loader': true,
                'sw-datepicker': true,
                'sw-text-editor': true,
            },
            provide: {
                validationService: {},
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve(),
                        get: () => Promise.resolve(),
                    }),
                },
            },
            attachTo: document.body,
        },
        props: {
            sets: createEntityCollection([
                {
                    id: 'set1',
                    name: 'set1',
                    config: {},
                    customFields: [{
                        name: 'field1',
                        type: 'text',
                        config: {
                            label: 'field1Label',
                        },
                    }],
                },
                {
                    id: 'set2',
                    name: 'set2',
                    config: {},
                    customFields: [{
                        name: 'field2',
                        type: 'bool',
                        config: {
                            label: 'field2Label',
                        },
                    }],
                },
            ]),
            ...customProps,
        },
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields', () => {
    let wrapper;

    it('should be the same data with isChanged when get currentIsChanged', async () => {
        wrapper = await createWrapper({
            isChanged: true,
        });

        expect(wrapper.vm.currentIsChanged).toBe(wrapper.vm.isChanged);
    });

    it('should be emit change event when user toggle to the change type field', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked();
        await flushPromises();

        expect(Object.keys(wrapper.vm.selectedCustomFields)).toHaveLength(1);
        expect(wrapper.emitted().change).toBeTruthy();
    });

    it('should only emit selected custom fields when user toggle to the change type field', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked(true);
        await flushPromises();

        expect(wrapper.emitted().change[0]).toBeTruthy();
        expect(Object.keys(wrapper.emitted().change[0])).toHaveLength(1);

        await changeToggle.find('.sw-field__checkbox input').setChecked(false);
        await flushPromises();

        expect(Object.keys(wrapper.vm.selectedCustomFields)).toHaveLength(0);
        expect(wrapper.emitted().change[1]).toBeTruthy();
        expect(Object.keys(wrapper.emitted().change[1][0])).toHaveLength(0);
    });

    it('should be get data from target input field of the customField only if its checked', async () => {
        wrapper = await createWrapper({
            entity: {
                customFields: {
                    field1: '',
                },
            },
        });
        await flushPromises();

        const customField = wrapper.find('#field1');
        await customField.setValue('this is a text field');
        await customField.trigger('input');
        await flushPromises();

        expect(wrapper.vm.entity.customFields.field1).toBe('this is a text field');
        expect(wrapper.vm.selectedCustomFields.field1).toBeUndefined();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked();
        await flushPromises();

        expect(wrapper.vm.entity.customFields.field1).toBe('this is a text field');
        expect(wrapper.vm.selectedCustomFields.field1).toBe('this is a text field');
    });
});
