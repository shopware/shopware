import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-chart-card';

function createWrapper(additionalOptions = {}) {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-chart-card'), {
        localVue,
        stubs: {
            'sw-card': true,
            'sw-select-field': true,
            'sw-chart': true,
        },
        ...additionalOptions
    });
}

describe('src/app/component/base/sw-chart-card', () => {
    it('validates the provided availableRanges prop', () => {
        const wrapper = createWrapper();
        const validator = wrapper.vm.$options.props.availableRanges.validator;

        const exactMatch = ['30Days', '14Days', '7Days', '24Hours', 'yesterday'];
        const invalidValue = ['30Days', '14Days', '5Days', '24Hours', 'yesterday'];
        const validSubset = ['30Days', '14Days', 'yesterday'];

        expect(validator(exactMatch)).toBeTruthy();
        expect(validator(invalidValue)).toBeFalsy();
        expect(validator(validSubset)).toBeTruthy();
    });

    it('properly checks for slot usage', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
        wrapper.vm.$slots['header-link'] = 'foo';

        expect(wrapper.vm.hasHeaderLink).toBeFalsy();
    });

    it('should emit "sw-chart-card-range-update" with current value of selectedRange property', () => {
        const expectedEvent = 'sw-chart-card-range-update';
        const expectedValue = '7Days';
        const wrapper = createWrapper();
        wrapper.setData({ selectedRange: expectedValue });

        wrapper.vm.$emit = jest.fn();
        wrapper.vm.dispatchRangeUpdate();

        expect(wrapper.vm.$emit).toBeCalledWith(expectedEvent, expectedValue);
    });
});
