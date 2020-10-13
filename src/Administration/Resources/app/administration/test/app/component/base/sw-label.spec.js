import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-label';

function createWrapper(propsData = {}, listeners = {}) {
    return shallowMount(Shopware.Component.build('sw-label'), {
        stubs: {
            'sw-icon': true
        },
        listeners,
        propsData: propsData
    });
}

describe('src/app/component/base/sw-label', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be dismissable', async () => {
        const wrapper = createWrapper({ dismissable: true }, { dismiss: () => {} });

        expect(wrapper.find('sw-label__dismiss')).toBeTruthy();
    });

    it('should not be dismissable', async () => {
        const wrapper = createWrapper({ dismissable: false }, { dismiss: () => {} });

        expect(wrapper.find('sw-label__dismiss').exists()).toBeFalsy();
    });
});
