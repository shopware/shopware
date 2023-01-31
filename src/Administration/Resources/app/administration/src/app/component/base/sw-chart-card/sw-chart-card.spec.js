/**
 * @package admin
 */

import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-chart-card';

async function createWrapper(additionalOptions = {}) {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-chart-card'), {
        localVue,
        propsData: {
            positionIdentifier: 'sw-chart-card__statistics-count',
        },
        stubs: {
            'sw-card': true,
            'sw-select-field': true,
            'sw-chart': true,
        },
        ...additionalOptions
    });
}

describe('src/app/component/base/sw-chart-card', () => {
    it('properly checks for slot usage', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
        wrapper.vm.$slots['header-link'] = 'foo';

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
    });

    it('should emit "sw-chart-card-range-update" with current value of selectedRange property', async () => {
        const expectedEvent = 'sw-chart-card-range-update';
        const expectedValue = '7Days';
        const wrapper = await createWrapper();
        await wrapper.setData({ selectedRange: expectedValue });

        wrapper.vm.$emit = jest.fn();
        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.vm.$emit).toBeCalledWith(expectedEvent, expectedValue);
    });

    it('should set the correct the position identifier from the prop to the card', async () => {
        const wrapper = await createWrapper();
        const swCard = wrapper.find('sw-card-stub');

        expect(swCard.attributes('position-identifier')).toBe('sw-chart-card__statistics-count');

        await wrapper.setProps({
            positionIdentifier: 'sw-dashboard-statistics__statistics-sum'
        });

        expect(swCard.attributes('position-identifier')).toBe('sw-dashboard-statistics__statistics-sum');
    });
});
