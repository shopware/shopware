import { mount } from '@vue/test-utils';
import swCmsVisibilityToggle from 'src/module/sw-cms/component/sw-cms-visibility-toggle';
import 'src/app/component/base/sw-icon';

/**
 * @package buyers-experience
 */

Shopware.Component.register('sw-cms-visibility-toggle', swCmsVisibilityToggle);

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-visibility-toggle', {
        sync: true,
    }), {
        props: {
            text: 'Toggle Text Button',
            isCollapsed: true,
        },
        global: {
            provide: {
                cmsService: {},
            },
            stubs: {
                'sw-icon': await Shopware.Component.build('sw-icon'),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
                'icons-regular-eye-slash': true,
                'icons-regular-chevron-down-xs': true,
                'icons-regular-chevron-up-xs': true,
            },
        },
    });
}

describe('module/sw-cms/component/sw-cms-visibility-toggle', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be collapsed', async () => {
        const wrapper = await createWrapper();
        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.sw-icon');
        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-down-xs');
    });

    it('should be expanded', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            isCollapsed: false,
        });

        const toggleButton = wrapper.find('.sw-cms-visibility-toggle__button');
        const collapsedIcon = toggleButton.find('.sw-icon');

        expect(collapsedIcon.classes()).toContain('icon--regular-chevron-up-xs');
    });
});
