import { mount } from '@vue/test-utils';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import type { ContentWorkspace } from '../../types/workspace.types';

/** @sw-package discovery */
describe('Experience Studio workspace dialogs', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;
    let store: ExperienceStudioWorkspaceStore;

    async function createWrapper() {
        // Exercise the page's template and dialog handlers without booting the layout editor.
        const component = (await wrapTestComponent('sw-experience-studio-detail', { sync: true })) as unknown as {
            template: string;
            data: () => Record<string, unknown>;
            computed: Record<string, () => unknown>;
            methods: Record<string, (...args: unknown[]) => unknown>;
        };

        return mount(
            {
                template: component.template,
                data() {
                    return { ...component.data(), isWorkspacePickerOpen: true };
                },
                computed: {
                    ...component.computed,
                    allowSave: () => true,
                    allowCreate: () => true,
                    layoutId: () => '',
                    availablePickerElements: () => [],
                    previewEntityType: () => null,
                },
                methods: component.methods,
            },
            {
                attachTo: document.body,
                global: {
                    provide: {
                        shortcutService: { startEventListener: jest.fn(), stopEventListener: jest.fn() },
                    },
                    stubs: {
                        'sw-page': { template: '<div><slot name="content" /></div>' },
                        'sw-experience-studio-toolbar': true,
                        'sw-experience-studio-canvas': true,
                        'sw-experience-studio-element-picker': true,
                        'sw-experience-studio-workspace-picker': await wrapTestComponent(
                            'sw-experience-studio-workspace-picker',
                            { sync: true },
                        ),
                        'sw-modal': await wrapTestComponent('sw-modal', { sync: true }),
                        'sw-avatar': true,
                    },
                },
            },
        );
    }

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['setImmediate'] });
        store = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        store.$reset();
        jest.spyOn(store, 'load').mockResolvedValue();
        store.activeWorkspace = { id: 'current', name: 'Current workspace' } as ContentWorkspace;
        store.workspaces = [{ id: 'other', name: 'Shared workspace' }] as ContentWorkspace[];
        store.total = 1;
    });

    afterEach(async () => {
        wrapper?.unmount();
        await jest.advanceTimersByTimeAsync(400);
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it('keeps the modal backdrop and search interactive while a row action menu is open', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const modal = document.body.querySelector<HTMLElement>('.sw-modal')!;
        const trigger = modal.querySelector<HTMLButtonElement>('.sw-experience-studio-workspace-picker__actions')!;
        trigger.click();
        await flushPromises();
        await jest.advanceTimersByTimeAsync(0);

        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(document.body.style.pointerEvents).not.toBe('none');
        expect(window.getComputedStyle(modal).pointerEvents).not.toBe('none');

        const search = modal.querySelector<HTMLInputElement>('input')!;
        search.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        search.focus();
        await flushPromises();

        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(search);
    });

    it('closes the workspace modal from its backdrop while a row action menu is open', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const modal = document.body.querySelector<HTMLElement>('.sw-modal')!;
        const trigger = modal.querySelector<HTMLButtonElement>('.sw-experience-studio-workspace-picker__actions')!;
        trigger.click();
        await flushPromises();
        await jest.advanceTimersByTimeAsync(0);

        expect(document.body.style.pointerEvents).not.toBe('none');
        modal.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));
        modal.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        await flushPromises();
        await jest.advanceTimersByTimeAsync(400);

        expect(document.body.querySelector('.sw-modal')).toBeNull();
        expect(document.body.querySelector('.mt-action-menu')).toBeNull();
        expect(document.body.style.pointerEvents).not.toBe('none');
    });

    it.each([
        [
            'rename',
            'sw-experience-studio.workspace.manager.rename',
        ],
        [
            'delete',
            'global.default.delete',
        ],
    ])('opens the %s dialog and returns to the list repeatedly using the real modal', async (action, label) => {
        wrapper = await createWrapper();
        await flushPromises();

        for (let attempt = 0; attempt < 2; attempt += 1) {
            const trigger = document.body.querySelector<HTMLButtonElement>(
                '.sw-experience-studio-workspace-picker__actions',
            )!;
            trigger.click();
            await flushPromises();

            const menuItem = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
                (item) => item.textContent?.trim() === label,
            );
            expect(menuItem).toBeDefined();
            menuItem!.click();
            await flushPromises();
            await jest.advanceTimersByTimeAsync(400);

            const dialog = document.body.querySelector<HTMLElement>('.sw-modal')!;
            expect(dialog.querySelector('.sw-modal__title')?.textContent?.trim()).toBe(
                action === 'rename'
                    ? 'sw-experience-studio.workspace.manager.rename'
                    : 'sw-experience-studio.workspace.manager.deleteTitle',
            );
            expect(dialog.querySelector<HTMLInputElement>('input')?.value).toBe(
                action === 'rename' ? 'Shared workspace' : undefined,
            );

            const cancel = Array.from(dialog.querySelectorAll('button')).find(
                (button) => button.textContent?.trim() === 'global.default.cancel',
            )!;
            cancel.click();
            await flushPromises();
            await jest.advanceTimersByTimeAsync(400);

            expect(document.body.querySelector('.sw-modal__title')?.textContent?.trim()).toBe(
                'sw-experience-studio.workspace.manager.manage',
            );
            expect(document.body.querySelector('.sw-experience-studio-workspace-picker__item')?.textContent).toContain(
                'Shared workspace',
            );
        }
    });
});
