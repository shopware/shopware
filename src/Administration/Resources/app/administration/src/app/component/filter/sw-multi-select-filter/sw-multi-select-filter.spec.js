/**
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const { Criteria, EntityCollection } = Shopware.Data;

const entities = [
    { id: 'id1', name: 'first' },
];

function getCollection() {
    return new EntityCollection(
        '/test-entity',
        'testEntity',
        null,
        { isShopwareContext: true },
        entities,
        entities.length,
        null,
    );
}
async function createWrapper(slots) {
    return mount(await wrapTestComponent('sw-multi-select-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': {
                    template: '<div class="sw-base-filter"><slot></slot></div>',
                    props: ['showResetButton'],
                },
                'sw-entity-multi-select': {
                    template: '<div class="sw-entity-multi-select"><slot name="selection-label-property"></slot><slot name="result-item"></slot></div>',
                    props: ['value', 'options', 'labelProperty', 'valueProperty', 'placeholder', 'displayVariants'],
                },
                'sw-multi-select': {
                    template: '<div class="sw-multi-select"><slot></slot></div>',
                    props: ['value', 'options', 'labelProperty', 'valueProperty'],
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => Promise.resolve(getCollection()),
                        };
                    },
                },
            },
        },
        slots,
        props: {
            filter: {
                name: 'category-filter',
                property: 'category',
                placeholder: 'placeholder',
                label: 'Test',
                schema: {
                    entity: 'entity',
                    referenceField: 'id',
                },
                value: null,
                filterCriteria: null,
            },
            active: true,
        },
    });
}

describe('src/app/component/filter/sw-multi-select-filter', () => {
    it('Should display title and placeholder', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-base-filter').attributes('title')).toBe('Test');
        expect(wrapper.getComponent('.sw-entity-multi-select').props('placeholder')).toBe('placeholder');
    });

    it('should emit `filter-update` event when user chooses entity', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent('.sw-entity-multi-select').vm.$emit('update:entity-collection', entities);

        const [name, criteria, value] = wrapper.emitted('filter-update')[0];

        expect(name).toBe('category-filter');
        expect(criteria).toEqual([Criteria.equalsAny('category.id', ['id1'])]);
        expect(value[0]).toEqual({ id: 'id1', name: 'first' });

        expect(wrapper.emitted('filter-reset')).toBeFalsy();
    });

    it('should emit `filter-reset` event when click Reset button', async () => {
        const wrapper = await createWrapper();

        const entityCollection = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
            { id: 'id2', name: 'item2' },
        ]);

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: entityCollection } });

        // Trigger click Reset button
        await wrapper.getComponent('.sw-base-filter').vm.$emit('filter-reset');
        expect(wrapper.emitted('filter-update')).toBeFalsy();
        expect(wrapper.emitted('filter-reset')).toBeTruthy();
    });

    it('should should pass `active` to the `sw-base-filter`', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ active: true });

        expect(wrapper.getComponent('.sw-base-filter').props('active')).toBe(true);
    });

    it('should display slot "selection-label-property" correct', async () => {
        const wrapper = await createWrapper({
            'selection-label-property': '<div class="selected-label">Selected label</div>',
        });

        await wrapper.setProps({
            filter: {
                name: 'category-filter',
                property: 'category',
                placeholder: 'placeholder',
                label: 'Test',
                schema: {
                    entity: 'entity',
                    referenceField: 'id',
                },
                value: [{ id: 'id1', name: 'first' }],
                filterCriteria: null,
            },
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.selected-label').exists()).toBeTruthy();
    });

    it('should display slot "result-item" correct', async () => {
        const wrapper = await createWrapper({
            'result-item': '<div class="result-item">List item</div>',
        });

        expect(wrapper.find('.result-item').text()).toBe('List item');
    });

    it('should display sw-multi-select if filter has options', async () => {
        const wrapper = await createWrapper();

        const filter = {
            name: 'category-filter',
            property: 'category',
            placeholder: 'placeholder',
            labelProperty: 'key',
            valueProperty: 'key',
            label: 'Test',
            value: null,
            filterCriteria: null,
            options: [
                { key: 'option1' },
                { key: 'option2' },
            ],
        };

        await wrapper.setProps({
            filter,
        });

        expect(wrapper.getComponent('.sw-multi-select').props('options')).toStrictEqual(filter.options);
    });

    it('should emit filter-update with correct value when filter is sw-multi-select', async () => {
        const wrapper = await createWrapper();

        const filter = {
            name: 'category-filter',
            property: 'category',
            placeholder: 'placeholder',
            labelProperty: 'key',
            valueProperty: 'key',
            label: 'Test',
            value: null,
            filterCriteria: null,
            options: [
                { key: 'option1' },
                { key: 'option2' },
            ],
        };

        await wrapper.setProps({
            filter,
        });

        await wrapper.getComponent('.sw-multi-select').vm.$emit('update:value', [filter.options[0].key]);
        expect(wrapper.emitted('filter-update')).toEqual([[
            'category-filter',
            [Criteria.equalsAny('category', ['option1'])],
            ['option1'],
        ]]);
    });

    it('should emit filter-update with correct value when filter has existing type', async () => {
        const wrapper = await createWrapper();

        const filter = {
            name: 'category-filter',
            property: 'category',
            placeholder: 'placeholder',
            labelProperty: 'key',
            valueProperty: 'key',
            label: 'Test',
            value: null,
            filterCriteria: null,
            options: [
                { key: 'option1' },
                { key: 'option2' },
            ],
            existingType: true,
        };

        await wrapper.setProps({
            filter,
        });

        await wrapper.getComponent('.sw-multi-select').vm.$emit('update:value', [filter.options[0].key]);

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'category-filter',
            [Criteria.multi('or', [Criteria.not('and', [Criteria.equals('option1.id', null)])])],
            ['option1'],
        ]);
    });

    it('should show product variant when displayVariants attribute of filter is true', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                name: 'line-item-filter',
                property: 'lineItems.product',
                placeholder: 'placeholder',
                labelProperty: 'name',
                label: 'Product',
                value: [
                    {
                        id: 'product1',
                        name: 'Product name 1',
                        variation: [{
                            group: 'color',
                            option: 'blue',
                        }],
                    },
                ],
                displayVariants: true,
                schema: {
                    entity: 'product',
                    referenceField: 'id',
                },
            },
        });

        expect(wrapper.getComponent('.sw-entity-multi-select').props('displayVariants')).toBe(true);
    });

    it('should reset filter if no value is selected', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                name: 'category-filter',
                property: 'category',
                placeholder: 'placeholder',
                labelProperty: 'key',
                valueProperty: 'key',
                label: 'Test',
                value: ['option1'],
                filterCriteria: null,
                options: [
                    { key: 'option1' },
                    { key: 'option2' },
                ],
                existingType: true,
            },
        });

        await wrapper.getComponent('.sw-multi-select').vm.$emit('update:value', []);

        expect(wrapper.emitted('filter-update')).toBeFalsy();
        expect(wrapper.emitted('filter-reset')[0]).toEqual([
            'category-filter',
        ]);
    });
});
