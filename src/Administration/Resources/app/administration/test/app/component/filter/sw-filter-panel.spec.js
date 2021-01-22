import 'src/app/component/filter/sw-filter-panel';
import 'src/app/component/filter/sw-boolean-filter';
import 'src/app/component/filter/sw-existence-filter';
import 'src/app/component/form/sw-select-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/filter/sw-base-filter';
import { shallowMount } from '@vue/test-utils';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-filter-panel'), {
        propsData: {
            title: 'Filter',
            entity: 'product',
            filters: [
                {
                    name: 'filter1',
                    type: 'boolean-filter',
                    label: 'filter1'
                },
                {
                    name: 'filter2',
                    type: 'existence-filter',
                    label: 'filter2',
                    schema: {
                        localField: 'id'
                    }
                },
                {
                    name: 'filter3',
                    type: 'multi-select-filter',
                    label: 'filter3'
                },
                {
                    name: 'filter4',
                    type: 'string-filter',
                    label: 'filter4'
                },
                {
                    name: 'filter5',
                    type: 'number-filter',
                    label: 'filter5'
                },
                {
                    name: 'filter6',
                    type: 'price-filter',
                    label: 'filter6'
                },
                {
                    name: 'filter7',
                    type: 'date-filter',
                    label: 'filter7'
                }
            ],
            defaults: ['filter1', 'filter2', 'filter3', 'filter4', 'filter5', 'filter6', 'filter7']
        },
        stubs: {
            'sw-boolean-filter': Shopware.Component.build('sw-boolean-filter'),
            'sw-select-field': Shopware.Component.build('sw-select-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-field-error': {
                template: '<div></div>'
            },
            'sw-icon': true,
            'sw-existence-filter': Shopware.Component.build('sw-existence-filter'),
            'sw-multi-select-filter': true,
            'sw-string-filter': true,
            'sw-price-filter': true,
            'sw-number-filter': true,
            'sw-date-filter': true
        },
        mocks: {
            $tc: key => key,
            $t: key => key
        }
    });
}

describe('components/sw-filter-panel', () => {
    it('should render filter components correctly', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.sw-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('sw-multi-select-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-string-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-number-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-date-filter-stub').exists()).toBeTruthy();
        expect(wrapper.find('sw-price-filter-stub').exists()).toBeTruthy();
    });


    it('should update filter with updated values', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('.sw-boolean-filter').findAll('option');

        options.at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeFilters.filter1).toBeTruthy();
    });

    it('should remove filter when reset button is clicked', async () => {
        const wrapper = createWrapper();

        const options = wrapper.find('.sw-boolean-filter').findAll('option');

        options.at(1).setSelected();

        await wrapper.vm.$nextTick();

        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.vm.activeFilters.filter1).toBeFalsy();
    });

    it('should display only default filters', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            defaults: ['filter1', 'filter2']
        });

        expect(wrapper.find('.sw-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('.sw-existence-filter').exists()).toBeTruthy();
        expect(wrapper.find('sw-multi-select-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-string-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-number-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-date-filter-stub').exists()).toBeFalsy();
        expect(wrapper.find('sw-price-filter-stub').exists()).toBeFalsy();
    });

    it('should reset all filters when `Reset All` button is clicked', async () => {
        const wrapper = createWrapper();

        wrapper.find('.sw-boolean-filter').findAll('option').at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(Object.keys(wrapper.vm.activeFilters).length).not.toEqual(0);

        await wrapper.vm.resetAll();

        expect(Object.keys(wrapper.vm.activeFilters).length).toEqual(0);
    });

    it('should display the number of active filters correctly', async () => {
        const wrapper = createWrapper();

        // Activate a filter
        wrapper.find('.sw-boolean-filter').findAll('option').at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['active-filter-number-update']).toBeTruthy();
        expect(wrapper.emitted()['active-filter-number-update'][0]).toEqual([1]);

        // Activate another filter
        wrapper.find('.sw-existence-filter').findAll('option').at(1).setSelected();

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['active-filter-number-update'][1]).toEqual([2]);

        // Check number of active filters when reset filters
        wrapper.find('.sw-base-filter__reset').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['active-filter-number-update'][2]).toEqual([1]);

        wrapper.find('.sw-base-filter__reset').trigger('click');

        await wrapper.vm.$nextTick();

        expect(wrapper.emitted()['active-filter-number-update'][3]).toEqual([0]);
    });
});
