import { mount } from '@vue/test-utils';

const { Criteria } = Shopware.Data;

async function createWrapper() {
    return mount(await wrapTestComponent('sw-string-filter', { sync: true }), {
        global: {
            stubs: {
                'sw-base-filter': await wrapTestComponent('sw-base-filter', { sync: true }),
            },
        },
        props: {
            filter: {
                property: 'code',
                name: 'promotionCode',
                label: 'Promotion Code',
                filterCriteria: null,
                value: null,
            },
            active: true,
        },
    });
}

describe('components/sw-string-filter', () => {
    it('should emit `filter-update` event with custom criteria filter', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({ criteriaFilterType: 'equals' });

        const input = wrapper.find('input');

        await input.setValue('cheap');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'promotionCode',
            [Criteria.equals('code', 'cheap')],
            'cheap',
        ]);
    });

    it('should emit `filter-update` event when value changes', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('input');

        await input.setValue('cheap');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-update'][0]).toEqual([
            'promotionCode',
            [Criteria.contains('code', 'cheap')],
            'cheap',
        ]);
    });

    it('should emit `filter-reset` event when user clicks Reset button', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ filter: { ...wrapper.vm.filter, value: 'cheap' } });

        // Trigger click Reset button
        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should emit `filter-reset` event when user empties value', async () => {
        const wrapper = await createWrapper();

        const input = wrapper.find('input');

        await input.setValue('');
        await input.trigger('change');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });
});
