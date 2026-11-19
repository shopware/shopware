import { mount } from '@vue/test-utils';
import { h } from 'vue';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-experience-studio-frame-menu', { sync: true }), {
        props: { label: 'Preview actions' },
        slots: {
            default: ({ popoverClass }: { popoverClass: string }) =>
                h('input', { 'aria-label': 'Preview entity', 'data-popover-class': popoverClass }),
        },
    });
}

describe('Experience Studio frame toolbar actions', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('renders preview settings inline and provides a class for their result portals', async () => {
        wrapper = await createWrapper();

        expect(wrapper.get('[role="group"]').attributes('aria-label')).toBe('Preview actions');
        expect(wrapper.get('input[aria-label="Preview entity"]').attributes('data-popover-class')).toMatch(
            /^experience-studio-frame-menu-.+-entity-results$/,
        );
        expect(wrapper.find('[aria-haspopup="dialog"]').exists()).toBe(false);
    });

    it.each([
        {
            action: 'translate',
            label: 'sw-experience-studio.detail.translationSettings.openAction',
            icon: 'regular-globe',
        },
        {
            action: 'open-another',
            label: 'sw-experience-studio.detail.canvas.openAnotherPreview',
            icon: 'regular-plus',
        },
    ])('exposes $action as an icon button with a tooltip and an accessible name', async ({ action, label, icon }) => {
        wrapper = await createWrapper();
        const button = wrapper.get(`.sw-experience-studio-frame-menu__${action}`);

        expect(button.attributes('aria-label')).toBe(label);
        expect(button.attributes('tooltip-mock-message')).toBe(label);
        expect(button.classes()).toEqual(
            expect.arrayContaining(['mt-button--tertiary', 'mt-button--x-small', 'mt-button--square']),
        );
        expect(button.find(`[data-testid="mt-icon__${icon}"]`).exists()).toBe(true);
        expect(button.text()).toBe('');

        await button.trigger('click');

        expect(wrapper.emitted(action)).toEqual([[]]);
    });
});
