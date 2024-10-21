import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-extension-adding-success', { sync: true }), {
        global: {
            stubs: {
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', {
                    sync: true,
                }),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                'sw-icon': true,
                'sw-label': true,
                'router-link': true,
                'sw-loader': true,
            },
        },
    });
}

/**
 * @package checkout
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
        await flushPromises();

        const closeButton = wrapper.getComponent('.sw-button');

        expect(closeButton.classes('sw-button--primary')).toBe(true);
        expect(closeButton.classes('sw-button--block')).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        const wrapper = await createWrapper();

        await wrapper.get('button.sw-button').trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });
});
