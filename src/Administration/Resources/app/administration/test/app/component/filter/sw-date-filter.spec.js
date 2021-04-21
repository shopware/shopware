import 'src/app/component/filter/sw-date-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/filter/sw-range-filter';

import { shallowMount, enableAutoDestroy } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-date-filter'), {
        stubs: {
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-range-filter': Shopware.Component.build('sw-range-filter'),
            'sw-datepicker': {
                props: ['value'],
                template: `
                    <div className="sw-field--datepicker">
                        <input type="text" ref="flatpickrInput" :value="value" @input="onChange">
                    </div>`,
                methods: {
                    onChange(e) {
                        this.$emit('input', e.target.value);
                    }
                }
            },
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            }
        },
        propsData: {
            filter: {
                property: 'releaseDate',
                name: 'releaseDate',
                label: 'Release Date'
            },
            active: true
        }
    });
}

enableAutoDestroy(afterEach);

describe('src/app/component/filter/sw-date-filter', () => {
    it('should emit `filter-update` event when `From` value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-date-filter__from').find('input');

        await input.setValue('2021-01-22');
        await input.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-22' })],
            { from: '2021-01-22', to: null }
        ]);
    });

    it('should emit `filter-update` event when `To` value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-date-filter__to').find('input');

        await input.setValue('2021-01-25');
        await input.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { lte: '2021-01-25' })],
            { from: null, to: '2021-01-25' }
        ]);
    });

    it('should emit `filter-update` event when `From` value `To` value exists', async () => {
        const wrapper = createWrapper();

        const fromInput = wrapper.find('.sw-date-filter__from').find('input');

        await fromInput.setValue('2021-01-19');
        await fromInput.trigger('input');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19' })],
            { from: '2021-01-19', to: null }
        ]);

        const toInput = wrapper.find('.sw-date-filter__to').find('input');

        await toInput.setValue('2021-01-25');
        await toInput.trigger('input');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'releaseDate',
            [Criteria.range('releaseDate', { gte: '2021-01-19', lte: '2021-01-25' })],
            { from: '2021-01-19', to: '2021-01-25' }
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
