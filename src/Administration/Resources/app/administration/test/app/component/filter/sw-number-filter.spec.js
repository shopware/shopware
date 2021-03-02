import 'src/app/component/filter/sw-number-filter';
import 'src/app/component/filter/sw-base-filter';
import 'src/app/component/filter/sw-range-filter';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import { shallowMount, enableAutoDestroy } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-number-filter'), {
        stubs: {
            'sw-base-filter': Shopware.Component.build('sw-base-filter'),
            'sw-range-filter': Shopware.Component.build('sw-range-filter'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-container': {
                template: '<div class="sw-container"><slot></slot></div>'
            },
            'sw-icon': true,
            'sw-field-error': {
                template: '<div></div>'
            }
        },
        propsData: {
            filter: {
                property: 'stock',
                name: 'stock',
                label: 'Stock',
                numberType: 'int',
                numberStep: 1,
                numberMin: 0
            },
            active: true
        },
        mocks: {
            $tc: key => key
        },
        provide: {
            validationService: {}
        }
    });
}

enableAutoDestroy(afterEach);

describe('components/sw-number-filter', () => {
    it('should emit `filter-update` event when user input `From` field', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-number-filter__from').find('input');

        // type "2"
        await input.setValue('2');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null }
        ]);
    });

    it('should emit `filter-update` event when user input `To` field', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-number-filter__to').find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { lte: 5 })],
            { from: null, to: 5 }
        ]);
    });


    it('should emit `filter-update` event when user input `From` field and `To` field', async () => {
        const wrapper = createWrapper();
        const fromInput = wrapper.find('.sw-number-filter__from').find('input');
        const toInput = wrapper.find('.sw-number-filter__to').find('input');

        await fromInput.setValue('2');
        await fromInput.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null }
        ]);

        await toInput.setValue('5');
        await toInput.trigger('change');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2, lte: 5 })],
            { from: 2, to: 5 }
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-number-filter__from').find('input');

        // type "2"
        await input.setValue('2');
        await input.trigger('change');

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const wrapper = createWrapper();
        const input = wrapper.find('.sw-number-filter__to').find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        // Trigger click Reset button
        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });
});
