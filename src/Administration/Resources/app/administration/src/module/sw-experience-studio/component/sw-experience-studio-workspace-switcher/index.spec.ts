import { mount } from '@vue/test-utils';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import type { ContentWorkspace } from '../../types/workspace.types';

/** @sw-package discovery */
describe('workspace switcher', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;
    let store: ExperienceStudioWorkspaceStore;
    let loadMock: jest.SpyInstance;
    let allowedRoles: string[];

    async function createWrapper(disabled = false) {
        return mount(await wrapTestComponent('sw-experience-studio-workspace-switcher', { sync: true }), {
            attachTo: document.body,
            props: { disabled },
            global: {
                stubs: { 'sw-avatar': await wrapTestComponent('sw-avatar', { sync: true }) },
                mocks: {
                    $t: (key: string, params?: { name: string }) => (params?.name ? `${key}: ${params.name}` : key),
                },
            },
        });
    }

    async function openMenu() {
        await wrapper.get('button').trigger('click');
        await flushPromises();
    }

    beforeEach(() => {
        allowedRoles = ['experience_studio.creator', 'experience_studio.editor'];
        jest.spyOn(Shopware.Service('acl'), 'can').mockImplementation((key) => !key || allowedRoles.includes(key));
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

    it('shows full creator names and falls back to usernames in the workspace menu', async () => {
        store.workspaces[0].createdBy = { firstName: 'Ada', lastName: 'Lovelace', username: 'ada' } as Entity<'user'>;
        store.workspaces[1].createdBy = { firstName: '', lastName: '', username: 'grace' } as Entity<'user'>;
        wrapper = await createWrapper();
        await openMenu();

        const creators = document.body.querySelectorAll('.sw-experience-studio-workspace-switcher__creator');
        expect(creators[0].textContent).toContain('Ada Lovelace');
        expect(creators[1].textContent).toContain('grace');
        const avatar = creators[0].nextElementSibling!;
        expect(avatar.classList.contains('sw-avatar__circle')).toBe(true);
        expect(avatar.querySelector('.sw-avatar__initials')?.textContent?.trim()).toBe('AL');
        expect(avatar.getAttribute('aria-hidden')).toBe('true');
    });

    it('shows an available profile photo beside the creator', async () => {
        store.workspaces[0].createdBy = {
            firstName: 'Ada',
            lastName: 'Lovelace',
            avatarMedia: { url: 'https://example.com/avatar.png' },
        } as Entity<'user'>;
        wrapper = await createWrapper();
        await openMenu();

        const avatar = document.body.querySelector('.sw-experience-studio-workspace-switcher__entry .sw-avatar')!;
        expect(avatar.getAttribute('style')).toContain('https://example.com/avatar.png');
        expect(avatar.querySelector('.sw-avatar__initials')).toBeNull();
    });

    it('shows a fallback for a deleted or unavailable creator', async () => {
        store.workspaces[0].createdBy = undefined;
        wrapper = await createWrapper();
        await openMenu();

        expect(document.body.querySelector('.sw-experience-studio-workspace-switcher__creator')?.textContent).toContain(
            'sw-experience-studio.workspace.manager.unknownCreator',
        );
        expect(document.body.querySelector('.sw-avatar [data-testid="mt-icon__regular-user"]')).not.toBeNull();
    });

    it('keeps browsing available to viewers but disables workspace creation', async () => {
        allowedRoles = ['experience_studio.viewer'];
        wrapper = await createWrapper();
        await openMenu();

        const create = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
            (item) => item.textContent?.trim() === 'sw-experience-studio.workspace.manager.create',
        )!;
        expect(create.getAttribute('aria-disabled')).toBe('true');
        create.click();
        await flushPromises();
        expect(wrapper.emitted('create')).toBeUndefined();

        const browse = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
            (item) => item.textContent?.trim() === 'sw-experience-studio.workspace.manager.manage',
        )!;
        browse.click();
        await flushPromises();
        expect(wrapper.emitted('browse')).toHaveLength(1);
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
        allowedRoles = ['experience_studio.viewer', 'experience_studio.editor'];
        store.activeWorkspace = null;
        store.workspaces = [];
        wrapper = await createWrapper();

        expect(wrapper.get('button').text()).toBe('sw-experience-studio.workspace.manager.title');
        await openMenu();

        const actions = document.body.querySelectorAll('[role="menuitem"]');
        expect(actions).toHaveLength(2);
        expect(actions[0].textContent).toContain('sw-experience-studio.workspace.manager.create');
        expect(actions[1].textContent).toContain('sw-experience-studio.workspace.manager.manage');
        expect(actions[0].getAttribute('aria-disabled')).not.toBe('true');
        (actions[0] as HTMLElement).click();
        await flushPromises();
        expect(wrapper.emitted('create')).toEqual([[]]);
    });
});
