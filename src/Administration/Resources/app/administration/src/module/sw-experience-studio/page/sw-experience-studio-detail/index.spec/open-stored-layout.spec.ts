import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('module/sw-experience-studio/page/sw-experience-studio-detail opening a stored layout', () => {
    const layout = {
        id: 'layout-product',
        rootSource: 'product',
        productContentLayouts: [{ productId: 'product-id', salesChannelId: 'sales-channel-id' }],
    };

    function createLayoutOpener(getEntityTypes: jest.Mock) {
        const vm = {
            availableLayoutTypes: [] as string[],
            layoutTypesRequest: null as Promise<void> | null,
            layoutTypeLoadError: null as string | null,
            isLoadingLayoutTypes: false,
            ensureLayoutTypesLoaded: methods.ensureLayoutTypesLoaded,
            loadLayoutTypes: methods.loadLayoutTypes,
            entityTypeService: () => ({ getEntityTypes }),
            openLayouts: [] as unknown[],
            frames: [] as unknown[],
            layout: null as typeof layout | null,
            isLoading: false,
            isLayoutPickerOpen: true,
            layoutRepository: { get: jest.fn().mockResolvedValue(layout) },
            layoutLoadCriteria: {},
            createOpenLayoutState: jest.fn().mockReturnValue({ id: layout.id, layout }),
            createPreviewFrame: methods.createPreviewFrame,
            previewLanguageContext: null,
            activateLayout: jest.fn(),
            applyPreviewContextDefaults: methods.applyPreviewContextDefaults,
            loadDefaultPreviewEntity: jest.fn().mockResolvedValue(undefined),
            syncActiveLayoutState: jest.fn(),
            diagnoseLayout: jest.fn(),
            $nextTick: jest.fn().mockResolvedValue(undefined),
            $refs: { experienceStudioCanvas: { fitCanvasToFrame: jest.fn(), fitCanvasToFrames: jest.fn() } },
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
            previewEntityId: null as string | null,
            previewSalesChannelId: null as string | null,
            getLayoutRootSource: methods.getLayoutRootSource,
            getFirstAssociationEntry: methods.getFirstAssociationEntry,
            resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
            resolvePreviewContext: methods.resolvePreviewContext,
        };
        vm.activateLayout.mockImplementation(() => {
            vm.layout = layout;
        });
        Object.defineProperties(vm, {
            resolvedPreviewContext: {
                get: () => methods.resolvePreviewContext.call(vm, vm.layout),
            },
        });

        return vm;
    }

    function pendingEntityTypes() {
        let resolveEntityTypes: (types: string[]) => void = () => undefined;
        const getEntityTypes = jest.fn().mockReturnValue(
            new Promise<string[]>((resolve) => {
                resolveEntityTypes = resolve;
            }),
        );

        return { getEntityTypes, resolveEntityTypes: (types: string[]) => resolveEntityTypes(types) };
    }

    it('fetches the layout while the entity type request is pending', async () => {
        const { getEntityTypes, resolveEntityTypes } = pendingEntityTypes();
        const vm = createLayoutOpener(getEntityTypes);

        const open = methods.openLayoutById.call(vm, layout.id);
        await flushPromises();
        expect(vm.layoutRepository.get).toHaveBeenCalledWith(layout.id, Shopware.Context.api, vm.layoutLoadCriteria);

        resolveEntityTypes(['product']);
        await open;
    });

    it('creates no frame before the entity type request settles', async () => {
        const { getEntityTypes, resolveEntityTypes } = pendingEntityTypes();
        const vm = createLayoutOpener(getEntityTypes);

        const open = methods.openLayoutById.call(vm, layout.id);
        await flushPromises();
        expect(vm.frames).toEqual([]);

        resolveEntityTypes(['product']);
        await open;

        expect(vm.frames).toHaveLength(1);
    });

    it('applies the assigned preview context of the layout', async () => {
        const vm = createLayoutOpener(jest.fn().mockResolvedValue(['product']));

        await methods.openLayoutById.call(vm, layout.id);

        expect(vm.previewEntityId).toBe('product-id');
        expect(vm.previewSalesChannelId).toBe('sales-channel-id');
    });

    it('retries a failed entity type request when the layout opens', async () => {
        const getEntityTypes = jest
            .fn()
            .mockRejectedValueOnce(new Error('Entity types unavailable'))
            .mockResolvedValueOnce(['product']);
        const vm = createLayoutOpener(getEntityTypes);
        await methods.ensureLayoutTypesLoaded.call(vm);

        await methods.openLayoutById.call(vm, layout.id);

        expect(vm.previewEntityId).toBe('product-id');
    });
});
