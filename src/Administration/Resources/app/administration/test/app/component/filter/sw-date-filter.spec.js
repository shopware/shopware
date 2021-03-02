import 'src/app/component/filter/sw-date-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/filter/sw-range-filter';
import 'src/app/component/form/sw-datepicker';

import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-date-filter'), {
        localVue,
        stubs: {
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-range-filter': Shopware.Component.build('sw-range-filter'),
            'sw-datepicker': Shopware.Component.build('sw-datepicker'),
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-contextual-field': {
                template: `
                <div class="sw-contextual-field">
                    <slot name="sw-field-input"></slot>
                    <slot name="sw-contextual-field-suffix"></slot>
                </div>`
            },
            'sw-icon': true,
            'sw-field-error': true
        },
        propsData: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date'
            },
            active: true
        },
        mocks: {
            $tc: key => key
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/app/component/filter/sw-date-filter', () => {
    it('should emit `filter-update` event when `From` value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: '2021-01-22',
                to: null
            }
        });

        const fields = wrapper.findAll('.flatpickr-input');

        expect(fields.at(0).attributes('value')).toEqual('2021-01-22T00:00:00+00:00');
        expect(fields.at(1).attributes('value')).toEqual('');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-22' })]
        ]);
    });

    it('should emit `filter-update` event when `To` value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: null,
                to: '2021-01-25'
            }
        });

        const fields = wrapper.findAll('.flatpickr-input');

        expect(fields.at(0).attributes('value')).toEqual('');
        expect(fields.at(1).attributes('value')).toEqual('2021-01-25T00:00:00+00:00');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { lte: '2021-01-25' })]
        ]);
    });

    it('should emit `filter-update` event when `From` value `To` value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: '2021-01-19',
                to: '2021-01-25'
            }
        });

        const fields = wrapper.findAll('.flatpickr-input');

        expect(fields.at(0).attributes('value')).toEqual('2021-01-19T00:00:00+00:00');
        expect(fields.at(1).attributes('value')).toEqual('2021-01-25T00:00:00+00:00');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19', lte: '2021-01-25' })]
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: '2021-01-22',
                to: null
            }
        });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.from).toBeNull();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            dateValue: {
                from: null,
                to: '2021-02-01'
            }
        });

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
        expect(wrapper.vm.dateValue.to).toBeNull();
    });

    it('should return default dateType of sw-datepicker', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'anytype'
            }
        });

        expect(wrapper.vm.dateType).toEqual('date');
    });

    it('should render From field and To field on the same line', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseTime',
                name: 'releaseTime',
                label: 'Release Time',
                dateType: 'time'
            }
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeTruthy();
        expect(container.attributes('columns')).toBe('1fr 12px 1fr');
    });

    it('should render From field and To field in different line', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date',
                dateType: 'datetime-local'
            }
        });

        const container = wrapper.find('.sw-container');
        const divider = wrapper.find('.sw-range-filter__divider');

        expect(divider.exists()).toBeFalsy();
        expect(container.attributes('columns')).toBe('1fr');
    });
});
