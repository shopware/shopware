/**
 * @group disabledCompat
 */
import { shallowMount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    return shallowMount(await wrapTestComponent('sw-number-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': await wrapTestComponent('sw-base-filter', { sync: true }),
                'sw-range-filter': await wrapTestComponent('sw-range-filter', { sync: true }),
                'sw-number-field': await wrapTestComponent('sw-number-field', { sync: true }),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-icon': true,
                'sw-field-error': {
                    template: '<div></div>',
                },
                'mt-number-field': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                validationService: {},
            },
        },
        props: {
            filter: {
                property: 'stock',
                name: 'stock',
                label: 'Stock',
                numberType: 'int',
                numberStep: 1,
                numberMin: 0,
            },
            active: true,
        },
    });
}

describe('components/sw-number-filter', () => {
    it('should emit `filter-update` event when user input `From` field', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-number-filter__from').find('input');

        // type "2"
        await input.setValue('2');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);
    });

    it('should emit `filter-update` event when user input `To` field', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-number-filter__to').find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { lte: 5 })],
            { from: null, to: 5 },
        ]);
    });


    it('should emit `filter-update` event when user input `From` field and `To` field', async () => {
        const wrapper = await createWrapper();
        const fromInput = wrapper.find('.sw-number-filter__from').find('input');
        const toInput = wrapper.find('.sw-number-filter__to').find('input');

        await fromInput.setValue('2');
        await fromInput.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);

        await toInput.setValue('5');
        await toInput.trigger('change');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2, lte: 5 })],
            { from: 2, to: 5 },
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-number-filter__from').find('input');

        // type "2"
        await input.setValue('2');
        await input.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const wrapper = await createWrapper();
        const input = wrapper.find('.sw-number-filter__to').find('input');

        // type "5"
        await input.setValue('5');
        await input.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });
});
