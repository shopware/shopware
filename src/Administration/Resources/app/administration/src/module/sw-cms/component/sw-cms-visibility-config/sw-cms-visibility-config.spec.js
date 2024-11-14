import { mount } from '@vue/test-utils';

/**
 * @package buyers-experience#
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-visibility-config', {
            sync: true,
        }),
        {
            propsData: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: true,
                },
            },
            global: {
                stubs: {
                    'sw-icon': await wrapTestComponent('sw-icon'),
                    'sw-icon-deprecated': true,
                },
            },
        },
    );
}

describe('module/sw-cms/component/sw-cms-visibility-config', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should be visible in all devices', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const mobileIcon = wrapper.findAll('sw-icon-deprecated-stub')[0];
        expect(mobileIcon.attributes('name')).toContain('regular-mobile');

        const tabletIcon = wrapper.findAll('sw-icon-deprecated-stub')[1];
        expect(tabletIcon.attributes('name')).toContain('regular-tablet');

        const desktopIcon = wrapper.findAll('sw-icon-deprecated-stub')[2];
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
        await flushPromises();

        const mobileIcon = wrapper.findAll('sw-icon-deprecated-stub')[0];
        expect(mobileIcon.attributes('name')).toContain('regular-mobile-slash');

        const tabletIcon = wrapper.findAll('sw-icon-deprecated-stub')[1];
        expect(tabletIcon.attributes('name')).toContain('regular-tablet-slash');

        const desktopIcon = wrapper.findAll('sw-icon-deprecated-stub')[2];
        expect(desktopIcon.attributes('name')).toContain('regular-desktop-slash');
    });

    it('should emit an event when the visibility changes', async () => {
        const wrapper = await createWrapper();
        await wrapper.get('#sw-cms-visibility-config-mobile').setChecked(true);
        await wrapper.get('#sw-cms-visibility-config-tablet').setChecked(true);
        await wrapper.get('#sw-cms-visibility-config-desktop').setChecked(true);

        expect(wrapper.emitted()['visibility-change']).toStrictEqual([
            [
                'mobile',
                false,
            ],
            [
                'tablet',
                false,
            ],
            [
                'desktop',
                false,
            ],
        ]);
    });
});
