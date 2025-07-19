import { mount } from '@vue/test-utils';

/**
 * @sw-package discovery
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

        const icons = wrapper.findAll('.mt-icon');

        expect(icons[0].classes()).toContain('icon--regular-mobile');
        expect(icons[1].classes()).toContain('icon--regular-tablet');
        expect(icons[2].classes()).toContain('icon--regular-desktop');
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

        const icons = wrapper.findAll('.mt-icon');

        expect(icons[0].classes()).toContain('icon--regular-mobile-slash');
        expect(icons[1].classes()).toContain('icon--regular-tablet-slash');
        expect(icons[2].classes()).toContain('icon--regular-desktop-slash');
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
