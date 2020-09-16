import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/mixin/sw-cms-state.mixin';
import 'src/module/sw-cms/component/sw-cms-slot';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-slot'), {
        localVue,
        propsData: {
            element: {}
        },
        stubs: {
            'foo-bar': true
        },
        mocks: {
            $tc: (value) => value
        },
        provide: {
            cmsService: {
                getCmsElementConfigByName: () => ({
                    component: 'foo-bar'
                })
            }
        }
    });
}
describe('module/sw-cms/component/sw-cms-slot', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('disable the custom component', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        expect(wrapper.classes()).toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBe('true');
    });

    it('enable the custom component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.classes()).not.toContain('is--disabled');

        const customComponent = wrapper.find('foo-bar-stub');
        expect(customComponent.attributes().disabled).toBeUndefined();
    });
});
