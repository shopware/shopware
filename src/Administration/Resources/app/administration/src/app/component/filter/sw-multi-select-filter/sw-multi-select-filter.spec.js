import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/filter/sw-multi-select-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-loader';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/base/sw-label';

const { Criteria, EntityCollection } = Shopware.Data;

let entities = [
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
async function createWrapper(customOptions) {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.directive('tooltip', {});

    const options = {
        localVue,
        stubs: {
            'sw-base-filter': await Shopware.Component.build('sw-base-filter'),
            'sw-entity-multi-select': await Shopware.Component.build('sw-entity-multi-select'),
            'sw-multi-select': await Shopware.Component.build('sw-multi-select'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-loader': await Shopware.Component.build('sw-loader'),
            'sw-popover': await Shopware.Component.build('sw-popover'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-icon': true,
            'sw-label': await Shopware.Component.build('sw-label'),
            'sw-product-variant-info': true,
            'sw-field-error': {
                template: '<div></div>',
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
        propsData: {
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
    };

    return shallowMount(await Shopware.Component.build('sw-multi-select-filter'), {
        ...options,
        ...customOptions,
    });
}

describe('src/app/component/filter/sw-multi-select-filter', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('Should display title and placeholder', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-base-filter .sw-base-filter__title').text()).toBe('Test');
        expect(wrapper.find('.sw-select-selection-list__input').attributes().placeholder).toBe('placeholder');
    });

    it('should emit `filter-update` event when user choose entity', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');
        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        await list.at(0).trigger('click');

        const [name, criteria, value] = wrapper.emitted()['filter-update'][0];

        expect(name).toBe('category-filter');
        expect(criteria).toEqual([Criteria.equalsAny('category.id', ['id1'])]);
        expect(value.first()).toEqual({ id: 'id1', name: 'first' });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });

    it('should emit `filter-reset` event when click Reset button', async () => {
        const wrapper = await createWrapper();

        const entityCollection = new EntityCollection(null, null, null, new Criteria(1, 25), [
            { id: 'id1', name: 'item1' },
            { id: 'id2', name: 'item2' },
        ]);

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: entityCollection } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');
        expect(wrapper.emitted()['filter-update']).toBeFalsy();
        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should reset the filter value when `active` is false', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');

        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        await list.at(0).trigger('click');

        await wrapper.setProps({ active: false });

        expect(wrapper.vm.values).toHaveLength(0);
        expect(wrapper.vm.filter.value).toBeNull();
        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not reset the filter value when `active` is true', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');

        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        await list.at(0).trigger('click');

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });

    it('should display slot "selection-label-property" correct', async () => {
        const wrapper = await createWrapper({
            slots: {
                'selection-label-property': '<div class="selected-label">Selected label</div>',
            },
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
            slots: {
                'result-item': 'List item',
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-select-result-list__item-list').text()).toBe('List item');
    });

    it('should display sw-multi-select if filter has options', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
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
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');

        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        expect(wrapper.find('.sw-multi-select').exists()).toBeTruthy();
        expect(list.at(0).text()).toBe('option1');
        expect(list.at(1).text()).toBe('option2');
    });

    it('should emit filter-update with correct value when filter is sw-multi-select', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
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
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');

        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        await list.at(0).trigger('click');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'category-filter',
            [Criteria.equalsAny('category', ['option1'])],
            ['option1'],
        ]);
    });

    it('should emit filter-update with correct value when filter has existing type', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
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
            },
        });

        await wrapper.find('.sw-select__selection').trigger('click');

        await wrapper.find('input').trigger('change');

        await wrapper.vm.$nextTick();

        const list = wrapper.find('.sw-select-result-list__item-list').findAll('li');

        await list.at(0).trigger('click');

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

        const entityMultiSelect = wrapper.find('.sw-entity-multi-select');
        expect(entityMultiSelect.exists()).toBeTruthy();

        const selectionList = wrapper.find('.sw-select-selection-list').findAll('li');
        expect(selectionList.at(0).find('sw-product-variant-info-stub').exists()).toBeTruthy();
    });

    it('should update product variant value when displayVariants attribute of filter is true', async () => {
        entities = [
            {
                id: 'product2',
                name: 'Product name 2',
                variation: [{
                    group: 'color',
                    option: 'red',
                }],
            },
        ];

        const wrapper = await createWrapper();

        await wrapper.setProps({
            filter: {
                name: 'line-item-filter',
                property: 'lineItems.product',
                placeholder: 'placeholder',
                labelProperty: 'name',
                label: 'Product',
                value: null,
                displayVariants: true,
                schema: {
                    entity: 'product',
                    referenceField: 'id',
                },
            },
        });


        await wrapper.find('.sw-select__selection').trigger('click');
        await wrapper.find('input').trigger('change');

        const resultList = wrapper.find('.sw-select-result-list__item-list').findAll('li');
        expect(resultList.at(0).find('sw-product-variant-info-stub').exists()).toBeTruthy();

        await resultList.at(0).trigger('click');

        const [name, criteria, value] = wrapper.emitted()['filter-update'][0];

        expect(name).toBe('line-item-filter');
        expect(criteria).toEqual([Criteria.equalsAny('lineItems.product.id', ['product2'])]);
        expect(value.first()).toEqual({
            id: 'product2',
            variation: [{
                group: 'color',
                option: 'red',
            }],
            name: 'Product name 2',
        });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
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

        await wrapper.find('.sw-label__dismiss').trigger('click');

        expect(wrapper.emitted()['filter-update']).toBeFalsy();
        expect(wrapper.emitted()['filter-reset'][0]).toEqual([
            'category-filter',
        ]);
    });
});
