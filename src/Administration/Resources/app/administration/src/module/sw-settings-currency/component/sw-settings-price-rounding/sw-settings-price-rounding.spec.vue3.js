/* eslint-disable max-len */
import { mount } from '@vue/test-utils_v3';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-price-rounding', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-container': true,
                'sw-switch-field': true,
                'sw-number-field': true,
                'sw-single-select': true,
                'sw-alert': true,
            },
        },
    });
}

describe('module/sw-settings-currency/component/sw-settings-price-rounding', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show info message when total rounding or item rounding interval is unequal to 0.01 or decimals are unequal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            totalRounding: {
                interval: 0.05,
                decimals: 2,
            },
            itemRounding: {
                interval: 0.10,
                decimals: 1,
            },
        });

        expect(wrapper.find('.sw-settings-price-rounding__header-info').exists()).toBeTruthy();
    });

    it('should not show info message when intervals are equal to 0.01 and decimals are equal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            totalRounding: {
                interval: 0.01,
                decimals: 2,
            },
            itemRounding: {
                interval: 0.01,
                decimals: 2,
            },
        });

        expect(wrapper.find('.sw-settings-price-rounding__header-info').exists()).toBeFalsy();
    });

    it('should show warning message when total and item rounding intervals are unequal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            totalRounding: {
                interval: 0.01,
            },
            itemRounding: {
                interval: 0.10,
            },
        });

        expect(wrapper.find('.sw-settings-price-rounding__header-warning').exists()).toBeTruthy();
    });

    it('should not show warning message when total and item rounding intervals are equal', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            totalRounding: {
                interval: 0.50,
            },
            itemRounding: {
                interval: 0.50,
            },
        });

        expect(wrapper.find('.sw-settings-price-rounding__header-warning').exists()).toBeFalsy();
    });
});

