import { shallowMount, createLocalVue } from '@vue/test-utils';
import swCmsVisibilityConfig from 'src/module/sw-cms/component/sw-cms-visibility-config';
import 'src/app/component/base/sw-icon';

/**
 * @package content
 */

Shopware.Component.register('sw-cms-visibility-config', swCmsVisibilityConfig);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-cms-visibility-config'), {
        localVue,
        propsData: {
            visibility: {
                mobile: true,
                tablet: true,
                desktop: true,
            }
        },
        provide: {
            cmsService: {}
        },
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'icons-regular-tablet': true,
            'icons-regular-mobile': true,
            'icons-regular-desktop': true,
            'icons-regular-tablet-slash': true,
            'icons-regular-mobile-slash': true,
            'icons-regular-desktop-slash': true,
        },
    });
}

describe('module/sw-cms/component/sw-cms-visibility-config', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be visible in all devices', async () => {
        const wrapper = await createWrapper();
        const mobileIcon = wrapper.findAll('.sw-icon').at(0);
        expect(mobileIcon.classes()).toContain('icon--regular-mobile');

        const tabletIcon = wrapper.findAll('.sw-icon').at(1);
        expect(tabletIcon.classes()).toContain('icon--regular-tablet');

        const desktopIcon = wrapper.findAll('.sw-icon').at(2);
        expect(desktopIcon.classes()).toContain('icon--regular-desktop');
    });

    it('should be invisible in all devices', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            visibility: {
                mobile: false,
                tablet: false,
                desktop: false,
            }
        });

        const mobileIcon = wrapper.findAll('.sw-icon').at(0);
        expect(mobileIcon.classes()).toContain('icon--regular-mobile-slash');

        const tabletIcon = wrapper.findAll('.sw-icon').at(1);
        expect(tabletIcon.classes()).toContain('icon--regular-tablet-slash');

        const desktopIcon = wrapper.findAll('.sw-icon').at(2);
        expect(desktopIcon.classes()).toContain('icon--regular-desktop-slash');
    });
});
