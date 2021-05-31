import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields';
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
import 'src/app/component/media/sw-media-field';
import 'src/app/component/media/sw-media-media-item';
import 'src/app/component/media/sw-media-base-item';
import 'src/app/component/media/sw-media-preview-v2';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createWrapper(customProps = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-bulk-edit-custom-fields'), {
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
                            label: 'field1Label'
                        }
                    }]
                },
                {
                    id: 'set2',
                    name: 'set2',
                    config: {},
                    customFields: [{
                        name: 'field2',
                        type: 'bool',
                        config: {
                            label: 'field2Label'
                        }
                    }]
                }
            ]),
            selectedCustomFields: {},
            ...customProps
        },
        stubs: {
            'sw-container': Shopware.Component.build('sw-container'),
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-entity-multi-select': true,
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': true,
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-multi-select': Shopware.Component.build('sw-multi-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-media-field': Shopware.Component.build('sw-media-field'),
            'sw-media-media-item': Shopware.Component.build('sw-media-media-item'),
            'sw-media-base-item': Shopware.Component.build('sw-media-base-item'),
            'sw-media-preview-v2': Shopware.Component.build('sw-media-preview-v2'),
            'sw-colorpicker': Shopware.Component.build('sw-text-field'),
            'sw-upload-listener': true,
            'sw-simple-search-field': true,
            'sw-loader': true,
            'sw-datepicker': true,
            'sw-text-editor': true
        },
        provide: {
            validationService: {}
        }
    });
}

describe('src/module/sw-bulk-edit/component/sw-bulk-edit-custom-fields', () => {
    let wrapper;

    beforeEach(() => {
        Shopware.Utils.debounce = () => {};
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        wrapper = createWrapper({
            sets: [],
            selectedCustomFields: {}
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be the same data with isChanged when get currentIsChanged', async () => {
        wrapper = createWrapper({
            isChanged: true
        });

        expect(wrapper.vm.currentIsChanged).toBe(wrapper.vm.isChanged);
    });

    it('should be able to add the target field to the selectedCustomFields when user toggle to the change type field', async () => {
        wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        changeToggle.find('.sw-field__checkbox input').trigger('click');

        expect(Object.keys(wrapper.vm.selectedCustomFields).length).toEqual(1);
        expect(wrapper.vm.selectedCustomFields.field1).toBe('');
        expect(wrapper.emitted('change')[0]).toEqual([true]);
    });

    it('should be able to remove the target field in the selectedCustomFields when user toggle to the change type field', async () => {
        wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        changeToggle.find('.sw-field__checkbox input').trigger('click');

        expect(wrapper.emitted('change')[0]).toEqual([true]);

        await wrapper.vm.$nextTick();

        changeToggle.find('.sw-field__checkbox input').trigger('click');

        expect(Object.keys(wrapper.vm.selectedCustomFields).length).toEqual(0);
        expect(wrapper.emitted('change')[1]).toEqual([false]);
    });

    it('should be get data from target input field of the customField', async () => {
        wrapper = createWrapper({
            entity: {
                customFields: {
                    field1: ''
                }
            }
        });

        const changeToggle = wrapper.find('.sw-bulk-edit-custom-fields__change');
        changeToggle.find('.sw-field__checkbox input').trigger('click');

        await wrapper.vm.$nextTick();

        const customField = wrapper.find('#field1');
        await customField.setValue('this is a text field');
        await customField.trigger('input');

        expect(wrapper.vm.selectedCustomFields.field1).toBe('this is a text field');
    });
});
