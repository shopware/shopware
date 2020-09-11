import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-stage-add-section';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-stage-add-section'), {
        localVue,
        propsData: {},
        stubs: {
            'sw-icon': true
        },
        mocks: {
            $tc: (value) => value
        },
        provide: {
            cmsService: {}
        }
    });
}

describe('module/sw-cms/component/sw-cms-stage-add-section', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('set a is--disabled class to wrapper', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        expect(wrapper.classes()).toContain('is--disabled');
    });

    it('do not set a is--disabled class to wrapper', async () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');
    });
});
