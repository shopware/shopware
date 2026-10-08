import type Repository from 'src/core/data/repository.data';
import type { ContentWorkspace, WorkspaceConfiguration } from '../types/workspace.types';
import { copyWorkspaceConfiguration, createWorkspaceConfiguration } from '../util/workspace-configuration.util';

const AUTOSAVE_DELAY = 500;
const { Criteria } = Shopware.Data;

function repository(): Repository<'content_workspace'> {
    return Shopware.Service('repositoryFactory').create('content_workspace');
}

/**
 * @private
 * @sw-package discovery
 */
const workspaceStore = Shopware.Store.register({
    id: 'experienceStudioWorkspace',
    state: () => ({
        activeWorkspace: null as ContentWorkspace | null,
        workspaces: [] as ContentWorkspace[],
        total: 0,
        isLoading: false,
        loadError: false,
        saveError: false,
        workspaceMissing: false,
        pendingConfiguration: null as WorkspaceConfiguration | null,
        savedConfiguration: '',
        saveTimer: null as ReturnType<typeof setTimeout> | null,
        saveRequest: null as Promise<boolean> | null,
    }),
    actions: {
        async load(page = 1, term = ''): Promise<void> {
            this.isLoading = true;
            this.loadError = false;
            try {
                const criteria = new Criteria(page, 25);
                criteria.addAssociation('createdBy.avatarMedia');
                criteria.addSorting(Criteria.sort('name', 'ASC'));
                if (term.trim()) {
                    criteria.setTerm(term.trim());
                }
                const result = await repository().search(criteria, Shopware.Context.api);
                this.workspaces = Array.from(result) as ContentWorkspace[];
                this.total = result.total ?? 0;
            } catch {
                this.loadError = true;
            } finally {
                this.isLoading = false;
            }
        },
        async get(id: string): Promise<ContentWorkspace> {
            const entity = await repository().get(id as EntityKey<'content_workspace'>, Shopware.Context.api);
            if (!entity) {
                throw Object.assign(new Error('Workspace no longer exists'), { code: 'WORKSPACE_NOT_FOUND' });
            }
            return entity as ContentWorkspace;
        },
        activate(workspace: ContentWorkspace | null): void {
            this.cancelTimer();
            this.activeWorkspace = workspace;
            this.pendingConfiguration = null;
            this.savedConfiguration = workspace ? JSON.stringify(copyWorkspaceConfiguration(workspace.configuration)) : '';
            this.saveError = false;
            this.workspaceMissing = false;
        },
        async create(name: string): Promise<ContentWorkspace> {
            const entity = repository().create(Shopware.Context.api) as ContentWorkspace;
            entity.name = name.trim();
            entity.configuration = createWorkspaceConfiguration();
            await repository().save(entity, Shopware.Context.api);
            return this.get(entity.id);
        },
        async rename(id: string, name: string): Promise<void> {
            const entity = await this.get(id);
            entity.name = name.trim();
            await repository().save(entity, Shopware.Context.api);
            const saved = await this.get(id);
            if (this.activeWorkspace?.id === id) {
                this.activeWorkspace = saved;
            }
            await this.load();
        },
        async remove(id: string): Promise<void> {
            if (this.activeWorkspace?.id === id) {
                if (!(await this.flush())) {
                    throw new Error('Workspace autosave failed');
                }
                this.cancelTimer();
            }
            await repository().delete(id as EntityKey<'content_workspace'>, Shopware.Context.api);
            if (this.activeWorkspace?.id === id) {
                this.activate(null);
            }
            await this.load();
        },
        schedule(configuration: WorkspaceConfiguration): void {
            if (!this.activeWorkspace) {
                return;
            }
            const snapshot = copyWorkspaceConfiguration(configuration);
            if (JSON.stringify(snapshot) === this.savedConfiguration && !this.saveRequest) {
                this.pendingConfiguration = null;
                this.cancelTimer();
                return;
            }
            this.pendingConfiguration = snapshot;
            this.cancelTimer();
            this.saveTimer = setTimeout(() => {
                void this.flush();
            }, AUTOSAVE_DELAY);
        },
        cancelTimer(): void {
            if (this.saveTimer !== null) {
                clearTimeout(this.saveTimer);
            }
            this.saveTimer = null;
        },
        async flush(): Promise<boolean> {
            this.cancelTimer();
            if (this.saveRequest) {
                if (!(await this.saveRequest)) {
                    return false;
                }
                return this.flush();
            }
            const workspaceId = this.activeWorkspace?.id;
            const snapshot = this.pendingConfiguration;
            if (!workspaceId || !snapshot) {
                return true;
            }
            this.pendingConfiguration = null;
            this.saveRequest = this.saveSnapshot(workspaceId, snapshot);
            const saved = await this.saveRequest;
            this.saveRequest = null;
            return saved ? this.flush() : false;
        },
        async saveSnapshot(id: string, snapshot: WorkspaceConfiguration): Promise<boolean> {
            try {
                // Read a fresh entity so this patch changes only configuration, preserving concurrent renames.
                const entity = await this.get(id);
                entity.configuration = copyWorkspaceConfiguration(snapshot);
                await repository().save(entity, Shopware.Context.api);
                const saved = await this.get(id);
                if (this.activeWorkspace?.id === id) {
                    this.activeWorkspace = saved;
                    this.savedConfiguration = JSON.stringify(snapshot);
                    this.saveError = false;
                }
                return true;
            } catch (error) {
                if (this.activeWorkspace?.id === id) {
                    const failure = error as { code?: string; response?: { status?: number } };
                    if (failure.code === 'WORKSPACE_NOT_FOUND' || failure.response?.status === 404) {
                        this.activate(null);
                        this.workspaceMissing = true;
                        return true;
                    }
                    this.pendingConfiguration ??= snapshot;
                    this.saveError = true;
                }
                return false;
            }
        },
    },
});

/** @private */
export type ExperienceStudioWorkspaceStore = ReturnType<typeof workspaceStore>;
/** @private */
export default workspaceStore;
