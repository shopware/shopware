import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-element.mixin';
import 'src/module/sw-cms/elements/sidebar-filter/component';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-el-sidebar-filter'), {
        localVue,
        propsData: {
            element: {}
        },
        stubs: {
            'sw-icon': true
        },
        mocks: {
            $tc: (value) => value
        },
        provide: {
            cmsService: {
                getCmsElementRegistry: () => ({
                    'sidebar-filter': {}
                })
            }
        }
    });
}

describe('src/module/sw-cms/elements/sidebar-filter/component', () => {
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
