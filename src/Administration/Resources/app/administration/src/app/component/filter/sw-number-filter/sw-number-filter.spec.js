/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-number-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': await wrapTestComponent('sw-base-filter', { sync: true }),
                'sw-range-filter': await wrapTestComponent('sw-range-filter', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field', {
                    sync: true,
                }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-field-error': {
                    template: '<div></div>',
                },
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
    await flushPromises();

    const inputFrom = wrapper.findByLabel('global.default.from');
    const inputTo = wrapper.findByLabel('global.default.to');

    return { wrapper, inputFrom, inputTo };
}

describe('components/sw-number-filter', () => {
    it('should emit `filter-update` event when user input `From` field', async () => {
        const { wrapper, inputFrom } = await createWrapper();

        // type "2"
        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);
    });

    it('should emit `filter-update` event when user input `To` field', async () => {
        const { wrapper, inputTo } = await createWrapper();

        // type "5"
        await inputTo.setValue('5');
        await inputTo.trigger('change');

        expect(wrapper.emitted('filter-update')[0]).toEqual([
            'stock',
            [Criteria.range('stock', { lte: 5 })],
            { from: null, to: 5 },
        ]);
    });

    it('should emit `filter-update` event when user input `From` field and `To` field', async () => {
        const { wrapper, inputFrom, inputTo } = await createWrapper();

        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        expect(wrapper.emitted('filter-update')[0]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2 })],
            { from: 2, to: null },
        ]);

        await inputTo.setValue('5');
        await inputTo.trigger('change');

        expect(wrapper.emitted()['filter-update'][1]).toEqual([
            'stock',
            [Criteria.range('stock', { gte: 2, lte: 5 })],
            { from: 2, to: 5 },
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button when from value exists', async () => {
        const { wrapper, inputFrom } = await createWrapper();

        // type "2"
        await inputFrom.setValue('2');
        await inputFrom.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user clicks Reset button when to value exists', async () => {
        const { wrapper, inputTo } = await createWrapper();

        // type "5"
        await inputTo.setValue('5');
        await inputTo.trigger('change');

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });
});
