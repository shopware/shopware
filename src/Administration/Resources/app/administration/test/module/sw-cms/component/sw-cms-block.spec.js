import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-block';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-block'), {
        localVue,
        propsData: {
            block: {}
        },
        stubs: {},
        mocks: {
            $tc: (value) => value
        },
        provide: {
            cmsService: {}
        }
    });
}
describe('module/sw-cms/component/sw-cms-block', () => {
    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('the overlay should exist and be visible', () => {
        const wrapper = createWrapper();

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeTruthy();
        expect(overlay.isVisible()).toBeTruthy();
    });

    it('the overlay should not exist', () => {
        const wrapper = createWrapper();
        wrapper.setProps({
            disabled: true
        });

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeFalsy();
    });
});
