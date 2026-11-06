import { mount } from '@vue/test-utils';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import type { ContentWorkspace } from '../../types/workspace.types';

describe('workspace picker', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;
    let store: ExperienceStudioWorkspaceStore;

    async function createWrapper() {
        return mount(await wrapTestComponent('sw-experience-studio-workspace-picker', { sync: true }), {
            attachTo: document.body,
            global: {
                stubs: {
                    'sw-modal': { template: '<div><slot /><slot name="modal-footer" /></div>' },
                    'sw-avatar': true,
                },
            },
        });
    }

    beforeEach(() => {
        store = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        store.$reset();
        jest.spyOn(store, 'load').mockResolvedValue();
        store.workspaces = [
            { id: 'current', name: 'Bedroom relaunch' },
            { id: 'shared', name: 'Autumn refresh', createdBy: { firstName: 'Mara', lastName: 'Jensen' } },
        ] as ContentWorkspace[];
        store.activeWorkspace = store.workspaces[0];
    });

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('lists shared workspaces and emits the selected ID', async () => {
        wrapper = await createWrapper();

        expect(wrapper.get('[title]').attributes('title')).toBe('sw-experience-studio.workspace.manager.manage');
        expect(wrapper.text()).toContain('Autumn refresh');
        expect(
            wrapper.findAll('.sw-experience-studio-workspace-picker__item [data-testid="mt-icon__regular-layout"]'),
        ).toHaveLength(2);
        await wrapper.findAll('.sw-experience-studio-workspace-picker__item')[1].trigger('click');
        expect(wrapper.emitted('select')).toEqual([['shared']]);
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
        await wrapper.findAll('.sw-experience-studio-workspace-picker__actions')[1].trigger('click');
        await flushPromises();

        const action = Array.from(document.body.querySelectorAll<HTMLElement>('[role="menuitem"]')).find(
            (item) => item.textContent?.trim() === label,
        );
        expect(action).toBeDefined();
        expect(action!.querySelector(`[data-testid="mt-icon__${icon}"]`)).not.toBeNull();
        action!.click();
        await flushPromises();

        expect(wrapper.emitted(event)).toEqual([[store.workspaces[1]]]);
        expect(wrapper.emitted('select')).toBeUndefined();
        expect(store.activeWorkspace?.id).toBe('current');
    });
});
