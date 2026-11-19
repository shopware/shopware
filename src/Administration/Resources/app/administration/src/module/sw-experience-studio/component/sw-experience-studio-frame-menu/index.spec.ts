import { DOMWrapper, mount } from '@vue/test-utils';
import { h, Teleport } from 'vue';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-experience-studio-frame-menu', { sync: true }), {
        attachTo: document.body,
        props: { label: 'Preview actions' },
        slots: {
            default: ({ popoverClass }: { popoverClass: string }) => [
                h('input', { 'aria-label': 'Preview entity' }),
                h(Teleport, { to: 'body' }, [h('button', { class: popoverClass }, 'Select product')]),
            ],
        },
    });
}

describe('Experience Studio frame menu', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper?.unmount();
    });

    async function openMenu() {
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');
        await flushPromises();
        return new DOMWrapper(document.body).get('.sw-experience-studio-frame-menu__content');
    }

    it('opens a labelled dialog for the preview controls and closes on Escape with focus returned to the icon button', async () => {
        wrapper = await createWrapper();
        const trigger = wrapper.get('button[aria-haspopup="dialog"]');
        expect(trigger.attributes('aria-expanded')).toBe('false');
        expect(document.body.querySelector('input[aria-label="Preview entity"]')).toBeNull();

        const dialog = await openMenu();
        expect(dialog.attributes('role')).toBe('dialog');
        expect(dialog.attributes('aria-label')).toBe('Preview actions');
        expect(document.activeElement).toBe(dialog.element);
        expect(trigger.attributes('aria-controls')).toBe(dialog.attributes('id'));
        await dialog.get('input').setValue('Product search');
        expect(trigger.attributes('aria-expanded')).toBe('true');

        await dialog.trigger('keydown', { key: 'Escape' });
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).toBeNull();
        expect(document.activeElement).toBe(trigger.element);
    });

    it('allows interaction with the entity picker result portal and dismisses on an unrelated outside click', async () => {
        wrapper = await createWrapper();
        await openMenu();
        const option = new DOMWrapper(document.body).get('button[class$="-entity-results"]');

        await option.trigger('pointerdown', { button: 0 });
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).not.toBeNull();
        await option.trigger('click');
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).not.toBeNull();
        await option.trigger('wheel', { deltaY: 100 });
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).not.toBeNull();
        await flushPromises();

        await new DOMWrapper(document.body).trigger('click');
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).toBeNull();
    });

    it('closes the flyout and requests another preview from its action', async () => {
        wrapper = await createWrapper();
        const dialog = await openMenu();

        await dialog.get('.sw-experience-studio-frame-menu__open-another').trigger('click');

        expect(wrapper.emitted('open-another')).toEqual([[]]);
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).toBeNull();
    });

    it('closes the flyout and requests layout translations from its action', async () => {
        wrapper = await createWrapper();
        const dialog = await openMenu();
        await dialog.get('.sw-experience-studio-frame-menu__translate').trigger('click');

        expect(wrapper.emitted('translate')).toEqual([[]]);
        expect(document.body.querySelector('.sw-experience-studio-frame-menu__content')).toBeNull();
    });
});
