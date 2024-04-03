import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

async function createWrapper(isSelectable, tooltip) {
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

    return mount(await wrapTestComponent('sw-entity-advanced-selection-modal-grid', {
        sync: true,
    }), {
        props: {
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

        global: {
            stubs: {
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-data-grid-settings': await wrapTestComponent('sw-data-grid-settings'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-icon': true,
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-context-menu-divider': true,
                'sw-pagination': await wrapTestComponent('sw-pagination'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
            },
            directives: {
                tooltip: {
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
                },
            },
        },
    });
}

describe('src/app/component/entity/sw-entity-advanced-selection-modal-grid', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should disable all checkboxes with enabled tooltip', async () => {
        const wrapper = await createWrapper(false, { message: 'test message', disabled: false });
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeTruthy();

        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('false');
    });

    it('should enable all checkboxes', async () => {
        const wrapper = await createWrapper(true);
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeFalsy();
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });

    it('should disable all checkboxes with disabled tooltip', async () => {
        const wrapper = await createWrapper(false, { message: 'test message', disabled: true });
        await flushPromises();

        const firstRowCheckbox = wrapper.find('.sw-data-grid__row--1').find('.sw-field--checkbox');

        expect(firstRowCheckbox.classes().includes('is--disabled')).toBeTruthy();
        expect(firstRowCheckbox.attributes('data-tooltip-message')).toBe('test message');
        expect(firstRowCheckbox.attributes('data-tooltip-disabled')).toBe('true');
    });
});
