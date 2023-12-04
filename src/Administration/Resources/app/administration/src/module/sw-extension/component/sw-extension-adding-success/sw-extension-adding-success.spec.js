import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-extension-adding-success', { sync: true }), {
        global: {
            stubs: {
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
            },
        },
    });
}

/**
 * @package services-settings
 */
describe('src/module/sw-extension/component/sw-extension-adding-success', () => {
    it('passes correct props to sw-circle-icon', async () => {
        const wrapper = await createWrapper();

        const swCircleIcon = wrapper.getComponent('.sw-circle-icon');

        expect(swCircleIcon.props('variant')).toBe('success');
        expect(swCircleIcon.props('size')).toBe(72);
        expect(swCircleIcon.props('iconName')).toBe('regular-checkmark');
    });

    it('has a primary block button', async () => {
        const wrapper = await createWrapper();

        const closeButton = wrapper.getComponent('.sw-button');

        expect(closeButton.props('variant')).toBe('primary');
        expect(closeButton.props('block')).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('button.sw-button').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });
});

