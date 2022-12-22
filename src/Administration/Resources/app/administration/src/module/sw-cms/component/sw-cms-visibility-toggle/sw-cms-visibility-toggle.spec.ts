import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-visibility-toggle/index';
import 'src/app/component/base/sw-icon';

/**
 * @package content
 */

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-visibility-toggle'), {
        localVue,
        propsData: {
            text: 'Toggle Text Button',
            isCollapsed: true,
        },
        provide: {
            cmsService: {}
        },
        stubs: {
            'sw-icon': Shopware.Component.build('sw-icon'),
            'icons-regular-eye-slash': true,
            'icons-regular-chevron-down-xs': true,
            'icons-regular-chevron-up-xs': true,
        },
    });
}

describe('module/sw-cms/component/sw-cms-visibility-toggle', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be collapsed', async () => {
        const wrapper = createWrapper();
        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.sw-icon');
        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-down-xs');
    });

    it('should be expanded', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            isCollapsed: false,
        });

        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.sw-icon');

        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-up-xs');
    });
});
