import { DOMWrapper, mount } from '@vue/test-utils';
import MtModal from '@shopware-ag/meteor-component-library/dist/esm/MtModal';
import MtModalRoot from '@shopware-ag/meteor-component-library/dist/esm/MtModalRoot';
import type { ComponentPublicInstance } from 'vue';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import type { ContentWorkspace } from '../../types/workspace.types';

describe('workspace picker', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;
    let store: ExperienceStudioWorkspaceStore;
    let loadWorkspaces: jest.SpyInstance;

    async function createWrapper() {
        const picker = mount(await wrapTestComponent('sw-experience-studio-workspace-picker', { sync: true }), {
            attachTo: document.body,
            global: {
                stubs: {
                    'mt-modal': MtModal,
                    'mt-modal-root': MtModalRoot,
                    'sw-avatar': true,
                    'sw-pagination': true,
                },
            },
        });
        await flushPromises();
        await jest.advanceTimersByTimeAsync(0);

        return picker;
    }

    function modal() {
        return new DOMWrapper(document.body).get('.mt-modal');
    }

    beforeEach(() => {
        jest.useFakeTimers({ doNotFake: ['setImmediate'] });
        store = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        store.$reset();
        loadWorkspaces = jest.spyOn(store, 'load').mockResolvedValue();
        store.workspaces = [
            { id: 'current', name: 'Bedroom relaunch' },
            { id: 'shared', name: 'Autumn refresh', createdBy: { firstName: 'Mara', lastName: 'Jensen' } },
        ] as ContentWorkspace[];
        store.activeWorkspace = store.workspaces[0];
    });

    afterEach(async () => {
        wrapper?.unmount();
        await jest.advanceTimersByTimeAsync(400);
        jest.useRealTimers();
        jest.restoreAllMocks();
    });

    it('lists shared workspaces and emits the selected ID', async () => {
        wrapper = await createWrapper();

        expect(modal().get('h2').text()).toBe('sw-experience-studio.workspace.manager.manage');
        expect(modal().text()).toContain('Autumn refresh');
        expect(
            modal().findAll('.sw-experience-studio-workspace-picker__item [data-testid="mt-icon__regular-layout"]'),
        ).toHaveLength(2);
        await modal().findAll('.sw-experience-studio-workspace-picker__item')[1].trigger('click');
        expect(wrapper.emitted('select')).toEqual([['shared']]);
    });

    it.each([
        'header',
        'backdrop',
        'Escape',
        'cancel',
    ])('closes the picker using %s', async (action) => {
        wrapper = await createWrapper();
        await flushPromises();
        expect(wrapper.emitted('close')).toBeUndefined();

        if (action === 'Escape') {
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        } else if (action === 'header') {
            await modal().get('.mt-modal__close-button').trigger('click');
        } else if (action === 'backdrop') {
            await new DOMWrapper(document.body).get('[data-testid="modal-backdrop"]').trigger('click');
        } else {
            const cancel = modal().findAll('.sw-experience-studio-workspace-picker__footer button')[0];
            await cancel.trigger('click');
        }
        await flushPromises();

        expect(wrapper.emitted('close')).toEqual([[]]);
    });

    it('offers creating a workspace in the modal footer', async () => {
        wrapper = await createWrapper();

        const create = modal().findAll('.sw-experience-studio-workspace-picker__footer button')[1];
        expect(create.text()).toBe('sw-experience-studio.workspace.manager.create');
        await create.trigger('click');

        expect(wrapper.emitted('create')).toEqual([[]]);
    });

    it('loads page two from the pagination payload', async () => {
        store.total = 30;
        wrapper = await createWrapper();
        const pagination = wrapper.getComponent<ComponentPublicInstance>('sw-pagination-stub');

        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();

        expect(loadWorkspaces).toHaveBeenLastCalledWith(2, '');
        expect(pagination.attributes('page')).toBe('2');
    });

    it('returns to page one on search and keeps the search term when paginating', async () => {
        store.total = 30;
        wrapper = await createWrapper();
        const pagination = wrapper.getComponent<ComponentPublicInstance>('sw-pagination-stub');
        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();
        const search = modal().get('input');
        search.element.focus();
        await search.setValue('Autumn');
        await flushPromises();
        expect(loadWorkspaces).toHaveBeenLastCalledWith(1, 'Autumn');
        expect(document.activeElement).toBe(modal().get('input').element);

        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();
        expect(loadWorkspaces).toHaveBeenLastCalledWith(2, 'Autumn');
        expect(pagination.attributes('page')).toBe('2');
    });

    it.each([
        [
            'rename',
            'sw-experience-studio.workspace.manager.rename',
            'regular-pencil-s',
        ],
        [
            'delete',
            'global.default.delete',
            'regular-trash',
        ],
    ])('offers %s for the chosen row without opening that workspace', async (event, label, icon) => {
        wrapper = await createWrapper();
        await modal().findAll('.sw-experience-studio-workspace-picker__actions')[1].trigger('click');
        await flushPromises();

        const action = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
            (item) => item.textContent?.trim() === label,
        );
        expect(action).toBeDefined();
        expect(modal().element.contains(action!)).toBe(true);
        action!.focus();
        expect(document.activeElement).toBe(action);
        expect(action!.querySelector(`[data-testid="mt-icon__${icon}"]`)).not.toBeNull();
        action!.click();
        await flushPromises();

        expect(wrapper.emitted(event)).toEqual([[store.workspaces[1]]]);
        expect(wrapper.emitted('select')).toBeUndefined();
        expect(store.activeWorkspace?.id).toBe('current');
    });
});
