/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swBulkEditCustomFields from 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/base/sw-container';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-highlight-text';
import SwMediaField from 'src/app/asyncComponent/media/sw-media-field';
import SwMediaMediaItem from 'src/app/asyncComponent/media/sw-media-media-item';
import SwMediaBaseItem from 'src/app/asyncComponent/media/sw-media-base-item';
import SwMediaPreviewV2 from 'src/app/asyncComponent/media/sw-media-preview-v2';

Shopware.Component.extend('sw-bulk-edit-custom-fields', 'sw-custom-field-set-renderer', swBulkEditCustomFields);
Shopware.Component.register('sw-media-field', SwMediaField);
Shopware.Component.register('sw-media-media-item', SwMediaMediaItem);
Shopware.Component.register('sw-media-base-item', SwMediaBaseItem);
Shopware.Component.register('sw-media-preview-v2', SwMediaPreviewV2);

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

async function createWrapper(customProps = {}) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-bulk-edit-custom-fields'), {
        localVue,
        propsData: {
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
        stubs: {
            'sw-container': await Shopware.Component.build('sw-container'),
            'sw-button': await Shopware.Component.build('sw-button'),
            'sw-label': await Shopware.Component.build('sw-label'),
            'sw-tabs': await Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': await Shopware.Component.build('sw-tabs-item'),
            'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': await Shopware.Component.build('sw-inheritance-switch'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-number-field': await Shopware.Component.build('sw-number-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-entity-multi-select': true,
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-icon': true,
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-multi-select': await Shopware.Component.build('sw-multi-select'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-media-field': await Shopware.Component.build('sw-media-field'),
            'sw-media-media-item': await Shopware.Component.build('sw-media-media-item'),
            'sw-media-base-item': await Shopware.Component.build('sw-media-base-item'),
            'sw-media-preview-v2': await Shopware.Component.build('sw-media-preview-v2'),
            'sw-colorpicker': await Shopware.Component.build('sw-text-field'),
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
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.Utils.debounce = () => {};
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper({
            sets: [],
            selectedCustomFields: {},
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be the same data with isChanged when get currentIsChanged', async () => {
        wrapper = await createWrapper({
            isChanged: true,
        });

        expect(wrapper.vm.currentIsChanged).toBe(wrapper.vm.isChanged);
    });

    it('should be emit change event when user toggle to the change type field', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked();

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.selectedCustomFields)).toHaveLength(1);
        expect(wrapper.emitted().change).toBeTruthy();
    });

    it('should only emit selected custom fields when user toggle to the change type field', async () => {
        wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted().change[0]).toBeTruthy();
        expect(Object.keys(wrapper.emitted().change[0][0])).toHaveLength(1);

        await changeToggle.find('.sw-field__checkbox input').trigger('click');

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.selectedCustomFields)).toHaveLength(0);

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

        const customField = wrapper.find('#field1');
        await customField.setValue('this is a text field');
        await customField.trigger('input');

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.entity.customFields.field1).toBe('this is a text field');
        expect(wrapper.vm.selectedCustomFields.field1).toBeUndefined();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        await changeToggle.find('.sw-field__checkbox input').setChecked();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.entity.customFields.field1).toBe('this is a text field');
        expect(wrapper.vm.selectedCustomFields.field1).toBe('this is a text field');
    });
});
