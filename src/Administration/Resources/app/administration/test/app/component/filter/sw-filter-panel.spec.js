import 'src/app/component/filter/sw-filter-panel';
import 'src/app/component/filter/sw-boolean-filter';
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
                    label: 'filter2'
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
            defaults: ['filter1']
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
            'sw-sidebar-filter-panel': true,
            'sw-icon': true,
            'sw-existence-filter': true,
            'sw-multi-select-filter': true,
            'sw-string-filter': true,
            'sw-price-filter': true,
            'sw-number-filter': true,
            'sw-date-filter': true
        },
        mocks: {
            $tc: key => key
        }
    });
}

describe('components/sw-filter-panel', () => {
    it('should render filter components correctly', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-boolean-filter').exists()).toBeTruthy();
        expect(wrapper.find('sw-existence-filter-stub').exists()).toBeTruthy();
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
});
