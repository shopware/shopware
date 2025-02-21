import { mount } from '@vue/test-utils';

async function createWrapper() {
    const wrapper = mount(await wrapTestComponent('sw-extension-adding-success', { sync: true }), {
        global: {
            stubs: {
                'sw-circle-icon': await wrapTestComponent('sw-circle-icon', { sync: true }),
                'sw-icon': true,
                'sw-label': true,
                'router-link': true,
                'sw-loader': true,
            },
        },
    });

    return {
        wrapper,
        closeButton: wrapper.findByText('button', 'global.default.close'),
    };
}

/**
 * @sw-package checkout
 */
describe('src/module/sw-extension/component/sw-extension-adding-success', () => {
    it('passes correct props to sw-circle-icon', async () => {
        const { wrapper } = await createWrapper();

        const swCircleIcon = wrapper.getComponent('.sw-circle-icon');

        expect(swCircleIcon.props('variant')).toBe('success');
        expect(swCircleIcon.props('size')).toBe(72);
        expect(swCircleIcon.props('iconName')).toBe('regular-checkmark');
    });

    it('has a primary block button', async () => {
        const { closeButton } = await createWrapper();
        await flushPromises();

        expect(closeButton.classes().some((cls) => cls.includes('--primary'))).toBe(true);
        expect(closeButton.classes().some((cls) => cls.includes('--block'))).toBe(true);
    });

    it('emits close if close button is clicked', async () => {
        const { wrapper, closeButton } = await createWrapper();

        await closeButton.trigger('click');

        expect(wrapper.emitted().close).toBeTruthy();
    });
});
