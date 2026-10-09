import detailComponent from './index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail workspace saving', () => {
    const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

    it('saves each dirty layout independently and keeps failures on their layout', async () => {
        const savedLayout = {
            id: 'layout-saved',
            name: 'Saved layout',
            rootSource: 'product',
            layout: [],
        };
        const failedLayout = {
            id: 'layout-failed',
            name: 'Failed layout',
            rootSource: 'category',
            layout: [],
        };
        const save = jest.fn((layout: { id: string }) => {
            if (layout.id === failedLayout.id) {
                return Promise.reject(new Error('Save failed'));
            }

            return Promise.resolve();
        });
        const openLayouts = [
            {
                id: savedLayout.id,
                layout: savedLayout,
                savedState: '',
                isNew: true,
                isSaving: false,
                saveError: null,
            },
            {
                id: failedLayout.id,
                layout: failedLayout,
                savedState: '',
                isNew: true,
                isSaving: false,
                saveError: null,
            },
        ];
        const notifyError = jest.fn();
        const vm = {
            allowSave: true,
            isSavingAll: false,
            openLayouts,
            layoutRepository: { save },
            syncActiveLayoutState: jest.fn(),
            isLayoutDirty: () => true,
            saveOpenLayout: methods.saveOpenLayout,
            serializeLayoutState: (layout: typeof savedLayout) => JSON.stringify(layout),
            getLayoutRootSource: (layout: typeof savedLayout) => layout.rootSource,
            extractApiErrorDetail: () => null,
            createNotificationError: notifyError,
            createNotificationSuccess: jest.fn(),
            $t: (key: string) => key,
        };

        await methods.saveAllOpenLayouts.call(vm);

        expect(save).toHaveBeenCalledTimes(2);
        expect(openLayouts[0].isNew).toBe(false);
        expect(openLayouts[0].savedState).toBe(JSON.stringify(savedLayout));
        expect(openLayouts[0].saveError).toBeNull();
        expect(openLayouts[1].isNew).toBe(true);
        expect(openLayouts[1].saveError).toBe('sw-experience-studio.detail.messageSaveError');
        expect(notifyError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.workspace.savePartialFailure',
        });
    });
});
