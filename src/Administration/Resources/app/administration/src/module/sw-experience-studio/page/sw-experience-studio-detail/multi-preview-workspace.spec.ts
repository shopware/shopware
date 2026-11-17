import detailComponent from './index';
import { createWorkspaceConfiguration, copyWorkspaceConfiguration } from '../../util/workspace-configuration.util';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('workspace persistence for several previews of one layout', () => {
    it('applies a delayed default entity to its originating frame without changing the active preview', async () => {
        let finish!: (value: { first: () => { id: string } }) => void;
        const search = jest.fn(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        );
        const origin = { id: 'origin', previewEntityId: null as string | null };
        const other = { id: 'other', previewEntityId: 'chosen-product' };
        const vm = {
            frames: [origin, other],
            activeFrameId: origin.id,
            previewEntityId: null as string | null,
            previewEntityType: 'product',
            repositoryFactory: { create: () => ({ search }) },
            defaultPreviewEntityCriteria: new Shopware.Data.Criteria(1, 1),
        };

        const loading = methods.loadDefaultPreviewEntity.call(vm);
        vm.activeFrameId = other.id;
        vm.previewEntityId = other.previewEntityId;
        finish({ first: () => ({ id: 'default-product' }) });
        await loading;

        expect(origin.previewEntityId).toBe('default-product');
        expect(other.previewEntityId).toBe('chosen-product');
        expect(vm.previewEntityId).toBe('chosen-product');
    });

    it('restores one shared document with independent frames, preserving their order and skipping deleted references', async () => {
        const configuration = copyWorkspaceConfiguration({
            ...createWorkspaceConfiguration(),
            frames: [
                {
                    id: 'desktop',
                    layoutId: 'shared',
                    x: 0,
                    y: 0,
                    width: 1480,
                    height: 900,
                    viewport: 'desktop',
                    previewEntityId: 'product-a',
                },
                {
                    id: 'mobile',
                    layoutId: 'shared',
                    x: 1000,
                    y: 20,
                    width: 375,
                    height: 700,
                    viewport: 'mobile',
                    previewEntityId: 'product-b',
                },
                {
                    id: 'missing',
                    layoutId: 'deleted',
                    x: 2000,
                    y: 0,
                    width: 600,
                    height: 900,
                    viewport: 'custom',
                    previewEntityId: null,
                },
            ],
        });
        const layout = { id: 'shared', name: 'Product page', rootSource: 'product', layout: [] };
        const search = jest.fn().mockResolvedValue([layout]);
        const vm = {
            layoutLoadCriteria: new Shopware.Data.Criteria(),
            layoutRepository: { search },
            createOpenLayoutState: methods.createOpenLayoutState,
            createPreviewFrame: methods.createPreviewFrame,
            serializeLayoutState: methods.serializeLayoutState,
            getLayoutRootSource: methods.getLayoutRootSource,
            restoreFramePreviewEntity: jest.fn().mockResolvedValue(false),
        };

        const restored = await methods.restoreWorkspaceLayouts.call(vm, configuration);

        expect(search).toHaveBeenCalledTimes(1);
        expect(vm.layoutLoadCriteria.ids).toEqual(['shared', 'deleted']);
        expect(restored).toEqual({
            openLayouts: [expect.objectContaining({ id: 'shared', layout })],
            frames: [
                expect.objectContaining({
                    id: 'desktop',
                    layoutId: 'shared',
                    viewport: 'desktop',
                    previewEntityId: 'product-a',
                }),
                expect.objectContaining({
                    id: 'mobile',
                    layoutId: 'shared',
                    viewport: 'mobile',
                    previewEntityId: 'product-b',
                }),
            ],
            missingReferences: true,
        });
    });

    it('persists every saved frame and omits all previews of an unsaved document', async () => {
        const configuration = copyWorkspaceConfiguration({
            ...createWorkspaceConfiguration(),
            frames: [
                'desktop',
                'mobile',
                'unsaved-a',
                'unsaved-b',
            ].map((id) => ({
                id,
                layoutId: id.startsWith('unsaved') ? 'unsaved' : 'shared',
                x: 0,
                y: 0,
                width: 600,
                height: 900,
                viewport: 'custom',
                previewEntityId: null,
            })),
        });
        const schedule = jest.fn();
        const vm = {
            allowSave: true,
            workspaceStore: { activeWorkspace: { id: 'workspace' }, schedule },
            $nextTick: jest.fn().mockResolvedValue(undefined),
            $refs: { experienceStudioCanvas: { getWorkspaceConfiguration: () => configuration } },
            frames: configuration.frames.map((frame) => ({ ...frame, previewEntityId: `entity-${frame.id}` })),
            openLayouts: [
                { id: 'shared', isNew: false },
                { id: 'unsaved', isNew: true },
            ],
            activeFrameId: 'mobile',
            currentViewport: 'mobile',
            previewEntityId: 'selected-product',
            previewSalesChannelId: 'channel',
        };

        await methods.scheduleWorkspaceSetup.call(vm);

        expect(schedule).toHaveBeenCalledWith(
            expect.objectContaining({
                frames: [
                    expect.objectContaining({ id: 'desktop', layoutId: 'shared', previewEntityId: 'entity-desktop' }),
                    expect.objectContaining({
                        id: 'mobile',
                        layoutId: 'shared',
                        viewport: 'mobile',
                        previewEntityId: 'selected-product',
                    }),
                ],
            }),
        );
    });
});
