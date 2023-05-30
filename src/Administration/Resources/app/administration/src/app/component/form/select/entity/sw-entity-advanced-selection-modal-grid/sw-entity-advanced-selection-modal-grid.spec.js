import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/data-grid/sw-data-grid-settings';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/form/select/entity/sw-entity-advanced-selection-modal-grid';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-base-field';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(isSelectable, tooltip) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
    });

    // mock entity functions
    const items = [
        { name: 'Apple' },
        { name: 'Shopware' },
        { name: 'Google' },
        { name: 'Microsoft' },
    ];
    items.total = 4;
    items.criteria = {
        page: 1,
        limit: 25,
    };

    return shallowMount(await Shopware.Component.build('sw-entity-advanced-selection-modal-grid'), {
        localVue,
        stubs: {
            'sw-entity-listing': await Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid-settings': await Shopware.Component.build('sw-data-grid-settings'),
            'sw-button': true,
            'sw-context-button': true,
            'sw-icon': true,
            'sw-field': true,
            'sw-switch-field': true,
            'sw-context-menu-divider': true,
            'sw-pagination': true,
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-context-menu-item': true,
            'sw-field-error': true,
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
        },
        provide: {},
        propsData: {
            isRecordSelectableCallback() {
                return { isSelectable, tooltip };
            },
            columns: [
                { property: 'name', label: 'Name' },
            ],
            items: new EntityCollection(null, null, null, new Criteria(1, 25), [
                { id: 'id1', name: 'item1' },
                { id: 'id2', name: 'item2' },
            ]),
            repository: {
                search: () => {},
            },
            detailRoute: 'sw.manufacturer.detail',
        },
    });
}

describe('src/app/component/entity/sw-entity-advanced-selection-modal-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all checkboxes with enabled tooltip', async () => {
        const wrapper = await createWrapper(false, { message: 'test message', disabled: false });
        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeTruthy();
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('false');
    });

    it('should enable all checkboxes', async () => {
        const wrapper = await createWrapper(true);
        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeFalsy();
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });

    it('should disable all checkboxes with disabled tooltip', async () => {
        const wrapper = await createWrapper(false, { message: 'test message', disabled: true });
        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeTruthy();
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });
});
