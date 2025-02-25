import { mount } from '@vue/test-utils';

/**
 * @sw-package discovery
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-visibility-toggle', {
            sync: true,
        }),
        {
            props: {
                text: 'Toggle Text Button',
                isCollapsed: true,
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-visibility-toggle', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be collapsed', async () => {
        const wrapper = await createWrapper();
        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.mt-icon');
        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-down-xs');
    });

    it('should be expanded', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isCollapsed: false,
        });

        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.mt-icon');

        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-up-xs');
    });
});
