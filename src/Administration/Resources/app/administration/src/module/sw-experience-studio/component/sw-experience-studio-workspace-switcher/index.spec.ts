import { mount } from '@vue/test-utils';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import type { ContentWorkspace } from '../../types/workspace.types';

/** @sw-package discovery */
describe('workspace switcher', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;
    let store: ExperienceStudioWorkspaceStore;
    let loadMock: jest.SpyInstance;

    async function createWrapper(disabled = false) {
        return mount(await wrapTestComponent('sw-experience-studio-workspace-switcher', { sync: true }), {
            attachTo: document.body,
            props: { disabled },
            global: { stubs: { 'sw-avatar': true } },
        });
    }

    async function openMenu() {
        await wrapper.get('button').trigger('click');
        await flushPromises();
    }

    beforeEach(() => {
        store = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        store.$reset();
        loadMock = jest.spyOn(store, 'load').mockResolvedValue();
        store.activeWorkspace = { id: 'current', name: 'Bedroom relaunch' } as ContentWorkspace;
        store.workspaces = [
            store.activeWorkspace,
            { id: 'other', name: 'Autumn refresh' },
        ] as ContentWorkspace[];
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('renders a Meteor button and menu with an icon for each workspace and management action', async () => {
        wrapper = await createWrapper();

        expect(wrapper.get('button.mt-button').text()).toBe('Bedroom relaunch');
        expect(wrapper.get('button').attributes('aria-haspopup')).toBe('menu');
        expect(wrapper.get('button').find('[data-testid="mt-icon__regular-layout"]').exists()).toBe(true);

        await openMenu();

        const menu = document.body.querySelector('.mt-action-menu')!;
        const entries = menu.querySelectorAll('.sw-experience-studio-workspace-switcher__entry');
        expect(entries).toHaveLength(2);
        expect(entries[0].querySelector('[data-testid="mt-icon__regular-layout"]')).not.toBeNull();
        expect(entries[1].querySelector('[data-testid="mt-icon__regular-layout"]')).not.toBeNull();
        expect(menu.querySelectorAll('[role="menuitem"][data-has-icon]')).toHaveLength(4);
        expect(
            Array.from(menu.querySelectorAll('[role="menuitem"]'))
                .slice(2)
                .map((item) => item.textContent?.trim()),
        ).toEqual([
            'sw-experience-studio.workspace.manager.create',
            'sw-experience-studio.workspace.manager.manage',
        ]);
    });

    it('highlights only the current workspace and updates the highlight when it changes', async () => {
        wrapper = await createWrapper();
        await openMenu();

        const entries = document.body.querySelectorAll('.sw-experience-studio-workspace-switcher__entry');
        expect(entries[0].classList.contains('is--active')).toBe(true);
        expect(entries[0].getAttribute('aria-current')).toBe('true');
        expect(entries[1].classList.contains('is--active')).toBe(false);
        expect(entries[1].getAttribute('aria-current')).toBeNull();

        store.activeWorkspace = store.workspaces[1];
        await flushPromises();

        expect(entries[0].classList.contains('is--active')).toBe(false);
        expect(entries[0].getAttribute('aria-current')).toBeNull();
        expect(entries[1].classList.contains('is--active')).toBe(true);
        expect(entries[1].getAttribute('aria-current')).toBe('true');
        expect(document.body.querySelector('[data-testid="mt-icon__regular-checkmark"]')).toBeNull();
    });

    it('selects another shared workspace and closes the menu without reloading the list again', async () => {
        wrapper = await createWrapper();
        await openMenu();

        const entries = document.body.querySelectorAll<HTMLElement>('.sw-experience-studio-workspace-switcher__entry');
        entries[1].click();
        await flushPromises();

        expect(wrapper.emitted('select')).toEqual([['other']]);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
        expect(loadMock).toHaveBeenCalledTimes(2);
    });

    it.each([
        [
            'browse',
            'sw-experience-studio.workspace.manager.manage',
        ],
        [
            'create',
            'sw-experience-studio.workspace.manager.create',
        ],
    ])('emits the %s action and closes the Meteor menu', async (event, label) => {
        wrapper = await createWrapper();
        await openMenu();

        const action = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
            (item) => item.textContent?.trim() === label,
        );
        expect(action).toBeDefined();
        action!.click();
        await flushPromises();

        expect(wrapper.emitted(event)).toHaveLength(1);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
    });

    it('supports opening and selecting a workspace with the keyboard', async () => {
        wrapper = await createWrapper();
        await wrapper.get('button').trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();

        const entry = document.body.querySelector<HTMLElement>('.sw-experience-studio-workspace-switcher__entry')!;
        entry.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        await flushPromises();

        expect(wrapper.emitted('select')).toEqual([['current']]);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
    });

    it('prevents opening a disabled switcher with the mouse or keyboard', async () => {
        wrapper = await createWrapper(true);

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        await openMenu();
        await wrapper.get('button').trigger('keydown', { key: 'ArrowDown' });
        await flushPromises();

        expect(wrapper.get('button').attributes('aria-expanded')).toBe('false');
        expect(loadMock).toHaveBeenCalledTimes(1);
    });

    it('offers browsing and creation when there is no active workspace', async () => {
        store.activeWorkspace = null;
        store.workspaces = [];
        wrapper = await createWrapper();

        expect(wrapper.get('button').text()).toBe('sw-experience-studio.workspace.manager.title');
        await openMenu();

        const actions = document.body.querySelectorAll('[role="menuitem"]');
        expect(actions).toHaveLength(2);
        expect(actions[0].textContent).toContain('sw-experience-studio.workspace.manager.create');
        expect(actions[1].textContent).toContain('sw-experience-studio.workspace.manager.manage');
    });
});
