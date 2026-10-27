import revisionsModal from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-revisions-modal', () => {
    const methods = (revisionsModal as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    function createVm<T extends Record<string, unknown>>(
        revisionService: Record<string, jest.Mock>,
        overrides: T = {} as T,
    ) {
        return {
            layoutId: 'layout-1',
            allowEdit: true,
            hasUnsavedChanges: false,
            published: 'live-1',
            isLoading: false,
            revisionPendingPublish: null as string | null,
            revisionPendingOpenAsDraft: null as string | null,
            revisionService: () => revisionService,
            openAsDraft: methods.openAsDraft,
            loadHistory: jest.fn(),
            createNotificationSuccess: jest.fn(),
            createNotificationError: jest.fn(),
            $emit: jest.fn(),
            $t: jest.fn((key: string) => key),
            ...overrides,
        };
    }

    it('opens a revision as draft right away when the editor has no unsaved changes', async () => {
        const revisionService = { createBranch: jest.fn().mockResolvedValue({ id: 'draft-1' }) };
        const vm = createVm(revisionService);

        methods.onOpenAsDraft.call(vm, 'revision-1');
        await flushPromises();

        expect(revisionService.createBranch).toHaveBeenCalledWith(
            'layout-1',
            expect.objectContaining({ fromRevisionId: 'revision-1' }),
        );
        expect(vm.$emit).toHaveBeenCalledWith('open-branch', 'draft-1');
        expect(vm.revisionPendingOpenAsDraft).toBeNull();
    });

    it('asks before opening a revision as draft while the editor has unsaved changes', async () => {
        const revisionService = { createBranch: jest.fn().mockResolvedValue({ id: 'draft-1' }) };
        const vm = createVm(revisionService, { hasUnsavedChanges: true });

        methods.onOpenAsDraft.call(vm, 'revision-1');
        await flushPromises();

        expect(vm.revisionPendingOpenAsDraft).toBe('revision-1');
        expect(revisionService.createBranch).not.toHaveBeenCalled();

        await methods.onConfirmOpenAsDraft.call(vm);

        expect(revisionService.createBranch).toHaveBeenCalledTimes(1);
        expect(vm.$emit).toHaveBeenCalledWith('open-branch', 'draft-1');
        expect(vm.revisionPendingOpenAsDraft).toBeNull();
    });

    it('creates no draft when opening as draft is cancelled', () => {
        const revisionService = { createBranch: jest.fn() };
        const vm = createVm(revisionService, { hasUnsavedChanges: true });

        methods.onOpenAsDraft.call(vm, 'revision-1');
        methods.onCancelOpenAsDraft.call(vm);

        expect(vm.revisionPendingOpenAsDraft).toBeNull();
        expect(revisionService.createBranch).not.toHaveBeenCalled();
        expect(vm.$emit).not.toHaveBeenCalled();
    });

    it('publishes against the live state the history showed', async () => {
        const revisionService = { publish: jest.fn().mockResolvedValue(undefined) };
        const vm = createVm(revisionService, { revisionPendingPublish: 'revision-1' });

        await methods.onConfirmPublish.call(vm);

        expect(revisionService.publish).toHaveBeenCalledWith('layout-1', {
            revisionId: 'revision-1',
            expectedPublished: 'live-1',
        });
        expect(vm.$emit).toHaveBeenCalledWith('published');
    });

    it('reports a publish refused because the live layout changed meanwhile and reloads the history', async () => {
        const revisionService = { publish: jest.fn().mockRejectedValue({ response: { status: 409 } }) };
        const vm = createVm(revisionService, { revisionPendingPublish: 'revision-1' });

        await methods.onConfirmPublish.call(vm);

        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.revisionsModal.messagePublishConflict',
        });
        expect(vm.loadHistory).toHaveBeenCalled();
        expect(vm.$emit).not.toHaveBeenCalledWith('published');
        expect(vm.isLoading).toBe(false);
    });
});
