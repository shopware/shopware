import { mount } from '@vue/test-utils_v3';

/**
 * @package content
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-cms-visibility-config', {
        sync: true,
    }), {
        propsData: {
            visibility: {
                mobile: true,
                tablet: true,
                desktop: true,
            },
        },
        provide: {
            cmsService: {},
        },
        stubs: {
            'sw-icon': await wrapTestComponent('sw-icon'),
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

        const mobileIcon = wrapper.findAll('sw-icon')[0];
        expect(mobileIcon.attributes('name')).toContain('regular-mobile');

        const tabletIcon = wrapper.findAll('sw-icon')[1];
        expect(tabletIcon.attributes('name')).toContain('regular-tablet');

        const desktopIcon = wrapper.findAll('sw-icon')[2];
        expect(desktopIcon.attributes('name')).toContain('regular-desktop');
    });

    it('should be invisible in all devices', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            visibility: {
                mobile: false,
                tablet: false,
                desktop: false,
            },
        });

        const mobileIcon = wrapper.findAll('sw-icon')[0];
        expect(mobileIcon.attributes('name')).toContain('regular-mobile-slash');

        const tabletIcon = wrapper.findAll('sw-icon')[1];
        expect(tabletIcon.attributes('name')).toContain('regular-tablet-slash');

        const desktopIcon = wrapper.findAll('sw-icon')[2];
        expect(desktopIcon.attributes('name')).toContain('regular-desktop-slash');
    });

    it('should emit an event when the visibility changes', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('#sw-cms-visibility-config-mobile').setChecked(true);
        await wrapper.get('#sw-cms-visibility-config-tablet').setChecked(true);
        await wrapper.get('#sw-cms-visibility-config-desktop').setChecked(true);

        expect(wrapper.emitted()['visibility-change']).toStrictEqual([['mobile', false], ['tablet', false], ['desktop', false]]);
    });
});
