import type { ContentLayoutBranch } from 'src/core/service/api/content-system-layout-revision.api.service';
import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail drafts', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    const branch = (id: string, head: string, name = 'Draft A'): ContentLayoutBranch => ({
        id,
        name,
        base: 'live',
        head,
        createdAt: '2026-10-27T10:00:00+00:00',
        updatedAt: '2026-10-27T10:00:00+00:00',
    });

    const conflict = { response: { status: 409 } };

    function createVm<T extends Record<string, unknown>>(
        revisionService: Record<string, jest.Mock>,
        overrides: T = {} as T,
    ) {
        return {
            layoutId: 'layout-1',
            layout: { id: 'layout-1', name: 'Landing page', layout: [] },
            branch: branch('draft-1', 'head-1'),
            lastSavedSnapshot: null,
            revisionService: () => revisionService,
            saveDraft: methods.saveDraft,
            saveToNewDraft: methods.saveToNewDraft,
            saveRevision: methods.saveRevision,
            applySavedRevision: methods.applySavedRevision,
            discardEmptyDraft: methods.discardEmptyDraft,
            getErrorStatus: methods.getErrorStatus,
            createLayoutSnapshot: methods.createLayoutSnapshot,
            createDefaultDraftName: () => 'Draft 27.10.2026',
            createNotificationWarning: jest.fn(),
            createNotificationError: jest.fn(),
            $t: jest.fn((key: string, values?: Record<string, string>) =>
                values ? `${key}:${JSON.stringify(values)}` : key,
            ),
            ...overrides,
        };
    }

    it('keeps the changes in a new draft from the conflicted head when the open draft was saved elsewhere', async () => {
        const forked = branch('draft-2', 'head-2', 'Draft 27.10.2026');
        const revisionService = {
            saveRevision: jest
                .fn()
                .mockRejectedValueOnce(conflict)
                .mockResolvedValueOnce({ branch: forked, layout: [], revision: { id: 'head-2' } }),
            createBranch: jest.fn().mockResolvedValue(branch('draft-2', 'head-1', 'Draft 27.10.2026')),
            deleteBranch: jest.fn(),
        };
        const vm = createVm(revisionService);

        const savedAsNewDraft = await methods.saveDraft.call(vm, vm.layout);

        expect(savedAsNewDraft).toBe(true);
        expect(revisionService.createBranch).toHaveBeenCalledWith('layout-1', {
            name: 'Draft 27.10.2026',
            fromRevisionId: 'head-1',
        });
        expect(revisionService.saveRevision).toHaveBeenLastCalledWith(
            'layout-1',
            'draft-2',
            expect.objectContaining({ expectedHead: 'head-1' }),
        );
        expect(vm.branch).toBe(forked);
        expect(vm.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messageDraftForked:{"previous":"Draft A","name":"Draft 27.10.2026"}',
        });
        expect(revisionService.deleteBranch).not.toHaveBeenCalled();
    });

    it('rethrows a save failure that is no conflict without creating a draft', async () => {
        const failure = { response: { status: 400 } };
        const revisionService = {
            saveRevision: jest.fn().mockRejectedValue(failure),
            createBranch: jest.fn(),
            deleteBranch: jest.fn(),
        };
        const vm = createVm(revisionService);

        await expect(methods.saveDraft.call(vm, vm.layout)).rejects.toBe(failure);
        expect(revisionService.createBranch).not.toHaveBeenCalled();
    });

    it('removes the new draft again when its first save fails', async () => {
        const failure = { response: { status: 400 } };
        const revisionService = {
            saveRevision: jest.fn().mockRejectedValueOnce(conflict).mockRejectedValueOnce(failure),
            createBranch: jest.fn().mockResolvedValue(branch('draft-2', 'head-1')),
            deleteBranch: jest.fn().mockResolvedValue(undefined),
        };
        const vm = createVm(revisionService);

        await expect(methods.saveDraft.call(vm, vm.layout)).rejects.toBe(failure);
        expect(revisionService.deleteBranch).toHaveBeenCalledWith('layout-1', 'draft-2');
    });

    it('reports a publish refused because the live layout changed as a conflict and stays on the draft', async () => {
        const revisionService = {
            publish: jest.fn().mockRejectedValue(conflict),
        };
        const vm = createVm(revisionService, {
            allowSave: true,
            isCreateMode: false,
            isDirty: false,
            hasDraft: true,
            routeBranchId: 'draft-1',
            hasRequiredLayoutMetadata: jest.fn(() => true),
            navigateToBranch: jest.fn(),
            isLoading: false,
        });

        await methods.onPublish.call(vm);

        expect(revisionService.publish).toHaveBeenCalledWith('layout-1', {
            revisionId: 'head-1',
            deleteBranchId: 'draft-1',
        });
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.messagePublishConflict',
        });
        expect(vm.navigateToBranch).not.toHaveBeenCalled();
        expect(vm.branch).toEqual(branch('draft-1', 'head-1'));
        expect(vm.isLoading).toBe(false);
    });
});
