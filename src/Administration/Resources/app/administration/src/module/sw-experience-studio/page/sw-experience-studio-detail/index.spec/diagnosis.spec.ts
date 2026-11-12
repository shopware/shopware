import type { ContentSystemViolation } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const violation: ContentSystemViolation = {
    code: 'invalid_mapping',
    scope: 'binding',
    severity: 'error',
    elementId: 'element-1',
    key: 'text',
    message: 'Invalid mapping',
    candidates: [],
};

const createVm = (diagnose: jest.Mock) => ({
    layout: { id: 'layout-1', name: 'Layout', layout: [] },
    diagnostics: [violation],
    diagnoseTimeoutId: null as number | null,
    diagnoseRequestSequence: 0,
    latestDiagnoseRequestId: 0,
    draftMutationService: () => ({ diagnose }),
    resolveMutationRootSource: () => 'product',
    createNotificationError: jest.fn(),
    $t: (key: string) => `translated:${key}`,
    diagnoseLayout() {
        return methods.diagnoseLayout.call(this);
    },
});

describe('Experience Studio diagnosis failures', () => {
    it('clears outdated findings and notifies when the current diagnosis fails', async () => {
        const vm = createVm(jest.fn().mockRejectedValue(new Error('Diagnosis unavailable')));

        await methods.diagnoseLayout.call(vm);

        expect(vm.diagnostics).toEqual([]);
        expect(vm.createNotificationError).toHaveBeenCalledTimes(1);
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'translated:sw-experience-studio.detail.messageDiagnoseError',
        });
    });

    it('quietly accepts a successful empty report', async () => {
        const vm = createVm(jest.fn().mockResolvedValue({ diagnostics: { violations: [] } }));

        await methods.diagnoseLayout.call(vm);

        expect(vm.diagnostics).toEqual([]);
        expect(vm.createNotificationError).not.toHaveBeenCalled();
    });

    it('ignores an older failure after a newer diagnosis has succeeded', async () => {
        let rejectOlder = () => {};
        const diagnose = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((_resolve, reject) => {
                        rejectOlder = () => reject(new Error('Old request failed'));
                    }),
            )
            .mockResolvedValueOnce({ diagnostics: { violations: [violation] } });
        const vm = createVm(diagnose);
        const older = methods.diagnoseLayout.call(vm);
        await methods.diagnoseLayout.call(vm);

        rejectOlder();
        await older;

        expect(vm.diagnostics).toEqual([violation]);
        expect(vm.createNotificationError).not.toHaveBeenCalled();
    });

    it('does not restore an old report after a newer diagnosis fails', async () => {
        let resolveOlder = () => {};
        const diagnose = jest
            .fn()
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveOlder = () => resolve({ diagnostics: { violations: [violation] } });
                    }),
            )
            .mockRejectedValueOnce(new Error('Current request failed'));
        const vm = createVm(diagnose);
        const older = methods.diagnoseLayout.call(vm);
        await methods.diagnoseLayout.call(vm);

        resolveOlder();
        await older;

        expect(vm.diagnostics).toEqual([]);
        expect(vm.createNotificationError).toHaveBeenCalledTimes(1);
    });

    it('ignores a failure invalidated by an edit before the debounced request starts', async () => {
        jest.useFakeTimers();
        let rejectOlder = () => {};
        const diagnose = jest.fn(
            () =>
                new Promise((_resolve, reject) => {
                    rejectOlder = () => reject(new Error('Old request failed'));
                }),
        );
        const vm = createVm(diagnose);

        try {
            const older = methods.diagnoseLayout.call(vm);
            methods.scheduleDiagnoseLayout.call(vm);
            rejectOlder();
            await older;

            expect(diagnose).toHaveBeenCalledTimes(1);
            expect(vm.diagnostics).toEqual([violation]);
            expect(vm.createNotificationError).not.toHaveBeenCalled();
        } finally {
            jest.clearAllTimers();
            jest.useRealTimers();
        }
    });

    it('reports a failed follow-up diagnosis after a refused save without changing the layout', async () => {
        const diagnosis = createVm(jest.fn().mockRejectedValue(new Error('Diagnosis unavailable')));
        const saveError = new Error('Save refused');
        const vm = {
            ...diagnosis,
            allowSave: true,
            layoutRootSource: 'product',
            layoutRepository: { save: jest.fn().mockRejectedValue(saveError) },
            notifySaveError: jest.fn(),
            showDiagnostics: false,
            isLoading: false,
        };
        const authoredLayout = structuredClone(vm.layout);

        await methods.onSave.call(vm);

        expect(vm.notifySaveError).toHaveBeenCalledWith(saveError);
        expect(vm.createNotificationError).toHaveBeenCalledWith({
            message: 'translated:sw-experience-studio.detail.messageDiagnoseError',
        });
        expect(vm.diagnostics).toEqual([]);
        expect(vm.showDiagnostics).toBe(true);
        expect(vm.isLoading).toBe(false);
        expect(vm.layout).toEqual(authoredLayout);
    });
});
