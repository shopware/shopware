/**
 * @group disabledCompat
 */
import 'src/app/component/filter/sw-filter-panel';
import 'src/app/component/filter/sw-boolean-filter';
import 'src/app/component/filter/sw-existence-filter';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/filter/sw-base-filter';
import { mount } from '@vue/test-utils';

const filters = [
    {
        name: 'filter1',
        type: 'boolean-filter',
        label: 'filter1',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter2',
        type: 'existence-filter',
        label: 'filter2',
        schema: {
            localField: 'id',
        },
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter3',
        type: 'multi-select-filter',
        label: 'filter3',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter4',
        type: 'string-filter',
        label: 'filter4',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter5',
        type: 'number-filter',
        label: 'filter5',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter6',
        type: 'price-filter',
        label: 'filter6',
        value: null,
        filterCriteria: null,
    },
    {
        name: 'filter7',
        type: 'date-filter',
        label: 'filter7',
        value: null,
        filterCriteria: null,
    },
];

let savedFilterData = {};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-filter-panel', { sync: true }), {
        props: {
            title: 'Filter',
            entity: 'product',
            filters,
            storeKey: 'config',
            defaults: ['filter1', 'filter2', 'filter3', 'filter4', 'filter5', 'filter6', 'filter7'],
        },
        global: {
            stubs: {
                'sw-boolean-filter': await Shopware.Component.build('sw-boolean-filter'),
                'sw-select-field': await wrapTestComponent('sw-select-field', { sync: true }),
                'sw-select-field-deprecated': await wrapTestComponent('sw-select-field-deprecated', { sync: true }),
                'sw-block-field': await Shopware.Component.build('sw-block-field'),
                'sw-base-field': await Shopware.Component.build('sw-base-field'),
                'sw-base-filter': await Shopware.Component.build('sw-base-filter'),
                'sw-field-error': {
                    template: '<div></div>',
                },
                'sw-icon': true,
                'sw-existence-filter': await Shopware.Component.build('sw-existence-filter'),
                'sw-multi-select-filter': true,
                'sw-string-filter': true,
                'sw-number-filter': true,
                'sw-date-filter': true,
                'sw-help-text': true,
                'sw-select-result': true,
                'sw-highlight-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-loader': true,
                'mt-select': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: () => Promise.resolve({
                            key: 'config',
                            userId: '1',
                        }),
                        search: () => Promise.resolve(savedFilterData),
                        save: () => Promise.resolve([]),
                    }),
                },
            },
        },
    });
}

Shopware.Service().register('filterService', () => {
    return {
        getStoredFilters: () => Promise.resolve(savedFilterData),
        saveFilters: (storeKey, storedFilters) => Promise.resolve(storedFilters),
    };
});

describe('components/sw-filter-panel', () => {
    it('should render filter components correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.sw-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('sw-multi-select-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-string-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-number-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-date-filter-stub').exists()).toBeTruthy();
    });


    it('should update filter with updated values', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        const options = wrapper.find('.sw-boolean-filter').findAll('option');

        await options.at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeFilters.filter1).toBeTruthy();
    });

    it('should remove filter when reset button is clicked', async () => {
        savedFilterData = {
            filter1: {},
        };

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const options = wrapper.find('.sw-boolean-filter').findAll('option');

        await options.at(0).setSelected();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.vm.activeFilters.filter1).toBeFalsy();
    });

    it('should display only default filters', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            defaults: ['filter1', 'filter2'],
        });

        expect(wrapper.find('.sw-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.sw-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('sw-multi-select-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-string-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-number-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-date-filter-stub').exists()).toBeFalsy();
    });

    it('should reset all filters when `Reset All` button is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        await wrapper.find('.sw-boolean-filter').findAll('option').at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.activeFilters)).not.toHaveLength(0);

        await wrapper.vm.resetAll();

        expect(Object.keys(wrapper.vm.activeFilters)).toHaveLength(0);
    });

    it('should change active filters when filter has default value', async () => {
        savedFilterData = {
            filter3: {
                value: [
                    {
                        id: '5e59f3ea47a342dd8ff1a0af2cda475',
                    },
                ],
                criteria: [{
                    type: 'equalsAny',
                    field: 'salutation.id',
                    value: '5e59f3ea47a342dd8ff1a0af2cda475',
                }],
            },
        };

        const wrapper = await createWrapper();

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.activeFilters)).toHaveLength(1);
    });
});
