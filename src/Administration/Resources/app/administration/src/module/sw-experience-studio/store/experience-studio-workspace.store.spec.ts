import type Repository from 'src/core/data/repository.data';
import type CriteriaType from 'src/core/data/criteria.data';
import './experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from './experience-studio-workspace.store';
import { createWorkspaceConfiguration } from '../util/workspace-configuration.util';
import type { ContentWorkspace } from '../types/workspace.types';

function workspace(id = 'workspace-a'): ContentWorkspace {
    return { id, name: id, configuration: createWorkspaceConfiguration() } as ContentWorkspace;
}

describe('Experience Studio workspaces', () => {
    let store: ExperienceStudioWorkspaceStore;
    let save: jest.Mock<Promise<void>, [ContentWorkspace, apiContext]>;
    let get: jest.Mock<Promise<ContentWorkspace>, [string]>;
    let search: jest.Mock<Promise<ContentWorkspace[] & { total: number }>, [CriteriaType, apiContext]>;
    beforeEach(() => {
        jest.useFakeTimers();
        store = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        store.$reset();
        jest.spyOn(Shopware.Service('acl'), 'can').mockImplementation((privilege) => privilege === 'user:read');
        save = jest.fn<Promise<void>, [ContentWorkspace, apiContext]>().mockResolvedValue(undefined);
        get = jest.fn((id: string) => Promise.resolve(workspace(id)));
        search = jest
            .fn<Promise<ContentWorkspace[] & { total: number }>, [CriteriaType, apiContext]>()
            .mockResolvedValue(Object.assign([workspace()], { total: 1 }));
        jest.spyOn(Shopware.Service('repositoryFactory'), 'create').mockReturnValue({
            get,
            save,
            search,
            delete: jest.fn(),
        } as unknown as Repository<keyof EntitySchema.EntityKeys>);
        store.activate(workspace());
    });
    afterEach(() => {
        jest.restoreAllMocks();
        store.cancelTimer();
        jest.useRealTimers();
    });

    it('loads creator information when the user already has permission to read users', async () => {
        const item = {
            ...workspace(),
            createdBy: { firstName: 'Ada', lastName: 'Lovelace', username: 'ada' },
        } as ContentWorkspace;
        search.mockResolvedValueOnce(Object.assign([item], { total: 1 }));
        await store.load(2, ' Autumn ');

        expect(search).toHaveBeenCalledWith(
            expect.objectContaining({ page: 2, limit: 25, term: 'Autumn' }),
            Shopware.Context.api,
        );
        expect(search.mock.calls[0][0].parse()).toHaveProperty('associations.createdBy');
        expect(search.mock.calls[0][0].parse()).not.toHaveProperty('associations.createdBy.associations.avatarMedia');
        expect(store.workspaces).toEqual([item]);
        expect(store.total).toBe(1);
        expect(store.loadError).toBe(false);
    });

    it('loads profile photos only when the user already has both user and media read permissions', async () => {
        jest.spyOn(Shopware.Service('acl'), 'can').mockImplementation(
            (privilege) => privilege === 'user:read' || privilege === 'media:read',
        );
        await store.load();

        expect(search.mock.calls[0][0].parse()).toHaveProperty('associations.createdBy.associations.avatarMedia');
    });

    it.each([false, true])(
        'keeps workspaces available without user access even when media read permission is %s',
        async (mediaRead) => {
            jest.spyOn(Shopware.Service('acl'), 'can').mockImplementation(
                (privilege) => mediaRead && privilege === 'media:read',
            );
            await store.load();

            expect(search.mock.calls[0][0].associations).toEqual([]);
            expect(store.workspaces).toEqual([workspace()]);
            expect(store.total).toBe(1);
            expect(store.loadError).toBe(false);
        },
    );

    it('coalesces edits and sends a captured setup without layout content', async () => {
        const first = createWorkspaceConfiguration();
        first.canvas.panX = 10;
        store.schedule(first);
        first.canvas.panX = 20;
        store.schedule(first);
        first.canvas.panX = 99;
        await jest.advanceTimersByTimeAsync(500);
        expect(save).toHaveBeenCalledTimes(1);
        expect(save.mock.calls[0][0].configuration.canvas.panX).toBe(20);
        expect(store.pendingConfiguration).toBeNull();
    });

    it('queues edits made during an in-flight save and flushes the latest snapshot', async () => {
        let finish: (() => void) | undefined;
        save.mockImplementationOnce(
            () =>
                new Promise<void>((resolve) => {
                    finish = resolve;
                }),
        );
        const configuration = createWorkspaceConfiguration();
        configuration.canvas.panX = 10;
        store.schedule(configuration);
        const saving = store.flush();
        await flushPromises();
        configuration.canvas.panX = 30;
        store.schedule(configuration);
        expect(save).toHaveBeenCalledTimes(1);
        finish!();
        expect(await saving).toBe(true);
        expect(save).toHaveBeenCalledTimes(2);
        expect(save.mock.calls[1][0].configuration.canvas.panX).toBe(30);
    });

    it('retains failed setup for retry without losing later edits', async () => {
        save.mockRejectedValueOnce(new Error('offline'));
        const configuration = createWorkspaceConfiguration();
        configuration.canvas.panX = 17;
        store.schedule(configuration);
        expect(await store.flush()).toBe(false);
        expect(store.saveError).toBe(true);
        expect(store.pendingConfiguration?.canvas.panX).toBe(17);
        expect(await store.flush()).toBe(true);
        expect(store.saveError).toBe(false);
    });

    it('does not apply an old save response to another active workspace', async () => {
        let finish: (() => void) | undefined;
        save.mockImplementationOnce(
            () =>
                new Promise<void>((resolve) => {
                    finish = resolve;
                }),
        );
        const configuration = createWorkspaceConfiguration();
        configuration.canvas.panX = 50;
        store.schedule(configuration);
        const saving = store.flush();
        await flushPromises();
        store.activate(workspace('workspace-b'));
        finish!();
        await saving;
        expect(store.activeWorkspace?.id).toBe('workspace-b');
        expect(save.mock.calls[0][0].id).toBe('workspace-a');
        expect(store.savedConfiguration).toBe(JSON.stringify(createWorkspaceConfiguration()));
    });

    it('skips unchanged setup and preserves server-side names during autosave', async () => {
        store.schedule(createWorkspaceConfiguration());
        await store.flush();
        expect(save).not.toHaveBeenCalled();
        get.mockImplementation((id: string) => Promise.resolve({ ...workspace(id), name: 'Renamed elsewhere' }));
        const configuration = createWorkspaceConfiguration();
        configuration.canvas.zoom = 0.5;
        store.schedule(configuration);
        await store.flush();
        expect(save.mock.calls[0][0].name).toBe('Renamed elsewhere');
    });
    it('recovers when another user deletes the workspace during autosave', async () => {
        get.mockResolvedValueOnce(null as unknown as ContentWorkspace);
        const configuration = createWorkspaceConfiguration();
        configuration.canvas.panX = 10;
        store.schedule(configuration);
        expect(await store.flush()).toBe(true);
        expect(store.activeWorkspace).toBeNull();
        expect(store.workspaceMissing).toBe(true);
        expect(store.pendingConfiguration).toBeNull();
        expect(save).not.toHaveBeenCalled();
    });

    it('renames only the name and reloads the entity for origin tracking', async () => {
        jest.spyOn(store, 'load').mockResolvedValue();
        await store.rename('workspace-a', ' New name ');
        expect(save.mock.calls[0][0].name).toBe('New name');
        expect(save.mock.calls[0][0].configuration).toEqual(createWorkspaceConfiguration());
        expect(get).toHaveBeenCalledTimes(2);
    });
});
