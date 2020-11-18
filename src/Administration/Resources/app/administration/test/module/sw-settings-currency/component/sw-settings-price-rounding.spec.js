import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-currency/component/sw-settings-price-rounding';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-price-rounding'), {
        localVue,
        mocks: {
            $tc: () => {},
            $device: {
                getSystemKey: () => {}
            }
        },
        provide: {
        },
        stubs: {
            'sw-container': true,
            'sw-switch-field': true,
            'sw-number-field': true,
            'sw-single-select': true
        }
    });
}

describe('module/sw-settings-currency/component/sw-settings-price-rounding', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBe(true);
    });
});

