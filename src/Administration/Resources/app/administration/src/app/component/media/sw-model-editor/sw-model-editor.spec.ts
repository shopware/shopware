/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package innovation
 */
import { mount } from '@vue/test-utils';

// Mock QuickView from @shopware-ag/dive/quickview
const mockQuickView = jest.fn();
const mockQuickViewDispose = jest.fn();
jest.mock('@shopware-ag/dive/quickview', () => ({
    QuickView: mockQuickView,
}));

// Mock Toolbox from @shopware-ag/dive/toolbox
const mockToolboxDispose = jest.fn();
const mockSetGizmoMode = jest.fn();
const mockEnableTool = jest.fn();
const mockSelect = jest.fn();
const mockToolbox = jest.fn().mockImplementation(() => ({
    dispose: mockToolboxDispose,
    enableTool: mockEnableTool,
    getTool: jest.fn().mockReturnValue({
        setGizmoMode: mockSetGizmoMode,
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
    }),
    selectionState: { select: mockSelect },
}));
jest.mock('@shopware-ag/dive/toolbox', () => ({
    Toolbox: mockToolbox,
}));

interface MockVector3 {
    x: number;
    y: number;
    z: number;
    clone(): MockVector3;
    equals(other: { x: number; y: number; z: number }): boolean;
}

interface MockEuler {
    x: number;
    y: number;
    z: number;
    clone(): MockEuler;
    equals(other: { x: number; y: number; z: number }): boolean;
}

const createMockVector3 = (x = 0, y = 0, z = 0): MockVector3 => ({
    x,
    y,
    z,
    clone() {
        return createMockVector3(this.x, this.y, this.z);
    },
    equals(other: { x: number; y: number; z: number }) {
        return this.x === other.x && this.y === other.y && this.z === other.z;
    },
});

const createMockEuler = (x = 0, y = 0, z = 0): MockEuler => ({
    x,
    y,
    z,
    clone() {
        return createMockEuler(this.x, this.y, this.z);
    },
    equals(other: { x: number; y: number; z: number }) {
        return this.x === other.x && this.y === other.y && this.z === other.z;
    },
});

const createMediaEntity = (overrides: Partial<EntitySchema.Entity<'media'>> = {}) => {
    return {
        getEntityName: () => 'media',
        id: 'test-media-id',
        url: 'https://example.com/model.glb',
        fileName: 'model.glb',
        fileExtension: 'glb',
        ...overrides,
    };
};

// Mock mediaService
const mockMediaService = {
    addUpload: jest.fn(),
    runUploads: jest.fn().mockResolvedValue(undefined),
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function createWrapper(componentConfig: any = {}) {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    return mount(await wrapTestComponent('sw-model-editor', { sync: true }), {
        props: {
            source: createMediaEntity(),
        },
        global: {
            stubs: {
                'mt-loader': {
                    template: '<div class="mt-loader"></div>',
                },
                'mt-icon': {
                    template: '<span class="mt-icon"></span>',
                },
                'sw-vector-field': {
                    template: '<div class="sw-vector-field"></div>',
                },
                'sw-model-editor-collapse': {
                    template: '<div class="sw-model-editor-collapse"></div>',
                },
                'sw-model-editor-collapse-title': {
                    template: '<div class="sw-model-editor-collapse-title"></div>',
                },
                'sw-model-editor-collapse-content': {
                    template: '<div class="sw-model-editor-collapse-content"></div>',
                },
                'sw-model-editor-collapse-button': {
                    template: '<div class="sw-model-editor-collapse-button"></div>',
                },
                'sw-model-editor-collapse-button-icon': {
                    template: '<div class="sw-model-editor-collapse-button-icon"></div>',
                },
                'sw-model-editor-collapse-button-text': {
                    template: '<div class="sw-model-editor-collapse-button-text"></div>',
                },
            },
            directives: {
                tooltip: {},
            },
            provide: {
                mediaService: mockMediaService,
            },
        },
        ...componentConfig,
    });
}

describe('src/app/component/media/sw-model-editor', () => {
    const mockScene = {
        root: {
            children: [
                {
                    isDIVEModel: true,
                    name: 'TestModel',
                    position: createMockVector3(),
                    rotation: createMockEuler(),
                    scale: createMockVector3(1, 1, 1),
                    setPosition: jest.fn(),
                    setRotation: jest.fn(),
                    setScale: jest.fn(),
                },
            ],
        },
    };

    const mockOrbitController = {};

    beforeEach(() => {
        jest.clearAllMocks();
        mockQuickView.mockResolvedValue({
            scene: mockScene,
            orbitController: mockOrbitController,
            dispose: mockQuickViewDispose,
        });
        mockMediaService.addUpload.mockClear();
        mockMediaService.runUploads.mockClear().mockResolvedValue(undefined);
    });

    describe('Component Initialization', () => {
        it('should mount successfully with valid media entity prop', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.exists()).toBe(true);
            expect(wrapper.vm.modelEntity).toBeTruthy();
        });

        it('should validate prop correctly for valid media entity', async () => {
            const validEntity = createMediaEntity();
            const wrapper = await createWrapper({
                props: {
                    source: validEntity,
                },
            });

            // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
            expect((wrapper.props() as any).source).toEqual(validEntity);
        });

        it('should initialize with default edit mode values', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.currentEditMode).toBe('translate');
            expect(wrapper.vm.isTranslatable).toBe(true);
            expect(wrapper.vm.isRotatable).toBe(true);
            expect(wrapper.vm.isScalable).toBe(true);
        });
    });

    describe('Lifecycle Hooks', () => {
        it('should find canvas element and initialize QuickView on mount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvas = wrapper.find('.sw-model-editor-canvas');
            expect(canvas.exists()).toBe(true);
            expect(wrapper.vm.canvas).toBeTruthy();
            expect(wrapper.vm.modelEntity).toBeTruthy();
        });

        it('should call initializeQuickView after mount', async () => {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper();
            await flushPromises();

            expect(mockQuickView).toHaveBeenCalled();
        });

        it('should handle missing canvas element gracefully', async () => {
            const wrapper = await createWrapper();
            // Manually set canvas to null to simulate missing element
            await wrapper.setData({ canvas: null });
            await flushPromises();

            // Component should still exist and not crash
            expect(wrapper.exists()).toBe(true);
        });
    });

    describe('QuickView Integration', () => {
        it('should initialize QuickView with correct parameters including axes and grid', async () => {
            const mediaEntity = createMediaEntity({
                url: 'https://example.com/test-model.glb',
            });
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper({
                props: {
                    source: mediaEntity,
                },
            });
            await flushPromises();

            expect(mockQuickView).toHaveBeenCalledWith('https://example.com/test-model.glb', {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                canvas: expect.any(HTMLCanvasElement),
                displayAxes: true,
                displayGrid: true,
            });
        });

        it('should set isLoading to false after QuickView completes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.isLoading).toBe(false);
        });

        it('should return early if canvas is null', async () => {
            const wrapper = await createWrapper();
            await flushPromises();
            const callCountBefore = mockQuickView.mock.calls.length;

            await wrapper.setData({ canvas: null });
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView().catch(() => {});
            await flushPromises();

            // QuickView should not be called again if canvas is null
            expect(mockQuickView.mock.calls).toHaveLength(callCountBefore);
        });

        it('should return early if modelEntity is null', async () => {
            const wrapper = await createWrapper();
            await flushPromises();
            const callCountBefore = mockQuickView.mock.calls.length;

            await wrapper.setData({ modelEntity: null });
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView().catch(() => {});
            await flushPromises();

            // QuickView should not be called again if modelEntity is null
            expect(mockQuickView.mock.calls).toHaveLength(callCountBefore);
        });

        it('should return early if modelEntity.url is missing', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const callCountBefore = mockQuickView.mock.calls.length;

            // Manually set URL to undefined and try to reinitialize
            await wrapper.setData({
                modelEntity: createMediaEntity({ url: undefined }),
            });
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView().catch(() => {});
            await flushPromises();

            // QuickView should not be called again if URL is missing
            expect(mockQuickView.mock.calls).toHaveLength(callCountBefore);
        });

        it('should handle QuickView errors gracefully', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();

            const wrapper = await createWrapper();
            await flushPromises();

            // Now reject for the next call
            mockQuickView.mockRejectedValueOnce(new Error('QuickView failed'));
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView().catch(() => {});
            await flushPromises();

            // Component should still exist and isLoading should be false
            expect(wrapper.exists()).toBe(true);
            expect(wrapper.vm.isLoading).toBe(false);

            consoleErrorSpy.mockRestore();
        });

        it('should set isLoading to false even when QuickView fails', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();

            const wrapper = await createWrapper();
            await flushPromises();

            // Now reject for the next call
            mockQuickView.mockRejectedValueOnce(new Error('QuickView failed'));
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView().catch(() => {});
            await flushPromises();

            expect(wrapper.vm.isLoading).toBe(false);

            consoleErrorSpy.mockRestore();
        });
    });

    describe('Toolbox Integration', () => {
        it('should create Toolbox after QuickView initialization', async () => {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper();
            await flushPromises();

            expect(mockToolbox).toHaveBeenCalledWith(mockScene, mockOrbitController);
        });

        it('should enable transform tool on initialization', async () => {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper();
            await flushPromises();

            expect(mockEnableTool).toHaveBeenCalledWith('transform');
        });

        it('should select the model after initialization', async () => {
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper();
            await flushPromises();

            expect(mockSelect).toHaveBeenCalledWith(expect.objectContaining({ isDIVEModel: true }));
        });

        it('should dispose toolbox on unmount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.unmount();

            expect(mockToolboxDispose).toHaveBeenCalled();
        });

        it('should dispose both quickView and toolbox on unmount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            wrapper.unmount();

            expect(mockToolboxDispose).toHaveBeenCalled();
            expect(mockQuickViewDispose).toHaveBeenCalled();
        });
    });

    describe('setGizmoMode Method', () => {
        it('should update currentEditMode when setGizmoMode is called', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('rotate');

            expect(wrapper.vm.currentEditMode).toBe('rotate');
        });

        it('should call toolbox.getTool("transform").setGizmoMode with correct mode', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('scale');

            expect(mockSetGizmoMode).toHaveBeenCalledWith('scale');
        });

        it('should handle translate mode', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('translate');

            expect(wrapper.vm.currentEditMode).toBe('translate');
            expect(mockSetGizmoMode).toHaveBeenCalledWith('translate');
        });

        it('should handle rotate mode', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('rotate');

            expect(wrapper.vm.currentEditMode).toBe('rotate');
            expect(mockSetGizmoMode).toHaveBeenCalledWith('rotate');
        });

        it('should handle scale mode', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('scale');

            expect(wrapper.vm.currentEditMode).toBe('scale');
            expect(mockSetGizmoMode).toHaveBeenCalledWith('scale');
        });
    });

    describe('Reactive Behavior', () => {
        it('should update modelEntity when source prop changes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const newMediaEntity = createMediaEntity({
                id: 'new-media-id',
                url: 'https://example.com/new-model.glb',
            });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-explicit-any
            await wrapper.setProps({ source: newMediaEntity } as any);
            await flushPromises();

            expect(wrapper.vm.modelEntity).toEqual(newMediaEntity);
        });

        it('should dispose and reinitialize QuickView when source prop changes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const initialCallCount = mockQuickView.mock.calls.length;

            const newMediaEntity = createMediaEntity({
                id: 'new-media-id',
                url: 'https://example.com/new-model.glb',
            });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-explicit-any
            await wrapper.setProps({ source: newMediaEntity } as any);
            await flushPromises();

            expect(mockQuickViewDispose).toHaveBeenCalled();
            expect(mockQuickView.mock.calls.length).toBeGreaterThan(initialCallCount);
        });

        it('should dispose toolbox when source prop changes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const newMediaEntity = createMediaEntity({
                id: 'new-media-id',
                url: 'https://example.com/new-model.glb',
            });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-explicit-any
            await wrapper.setProps({ source: newMediaEntity } as any);
            await flushPromises();

            expect(mockToolboxDispose).toHaveBeenCalled();
        });
    });

    describe('Template Rendering', () => {
        it('should render canvas element with correct class', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvas = wrapper.find('.sw-model-editor-canvas');
            expect(canvas.exists()).toBe(true);
            expect(canvas.classes()).toContain('sw-model-editor-canvas');
        });

        it('should show loader when isLoading is true', async () => {
            const wrapper = await createWrapper();
            // Before initialization completes
            expect(wrapper.vm.isLoading).toBe(true);

            const loader = wrapper.find('.mt-loader');
            expect(loader.exists()).toBe(true);
        });

        it('should hide loader when isLoading is false', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.isLoading).toBe(false);
            const loader = wrapper.find('.mt-loader');
            expect(loader.exists()).toBe(false);
        });

        it('should render wrapper divs with correct classes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-model-editor').exists()).toBe(true);
            expect(wrapper.find('.sw-model-editor-canvas-wrapper').exists()).toBe(true);
        });

        it('should render edit options container', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.find('.sw-model-editor-canvas-ui__edit-options').exists()).toBe(true);
        });

        it('should render three edit mode buttons', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            expect(buttons).toHaveLength(3);
        });

        it('should mark translate button as active by default', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttonContainers = wrapper.findAll('.sw-model-editor-canvas-ui__button-container');
            expect(buttonContainers[0].classes()).toContain('is--active');
        });

        it('should update active class when edit mode changes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).setGizmoMode('rotate');
            await wrapper.vm.$nextTick();

            const buttonContainers = wrapper.findAll('.sw-model-editor-canvas-ui__button-container');
            expect(buttonContainers[0].classes()).not.toContain('is--active');
            expect(buttonContainers[1].classes()).toContain('is--active');
        });

        it('should disable scale button when isScalable is false', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            // Set isScalable to false to test disabled state
            await wrapper.setData({ isScalable: false });

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            const scaleButton = buttons[2];

            expect(scaleButton.attributes('disabled')).toBeDefined();
        });

        it('should enable translate button when isTranslatable is true', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            const translateButton = buttons[0];

            expect(translateButton.attributes('disabled')).toBeUndefined();
        });

        it('should enable rotate button when isRotatable is true', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            const rotateButton = buttons[1];

            expect(rotateButton.attributes('disabled')).toBeUndefined();
        });
    });

    describe('Button Click Events', () => {
        it('should call setGizmoMode with translate when translate button is clicked', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            await buttons[0].trigger('click');

            expect(mockSetGizmoMode).toHaveBeenCalledWith('translate');
        });

        it('should call setGizmoMode with rotate when rotate button is clicked', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            await buttons[1].trigger('click');

            expect(mockSetGizmoMode).toHaveBeenCalledWith('rotate');
        });

        it('should call setGizmoMode with scale when scale button is clicked', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            // isScalable is true by default, so scale button should be enabled
            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            await buttons[2].trigger('click');

            expect(mockSetGizmoMode).toHaveBeenCalledWith('scale');
        });
    });

    describe('Integration Scenarios', () => {
        it('should complete full initialization flow', async () => {
            const mediaEntity = createMediaEntity({
                id: 'integration-test-id',
                url: 'https://example.com/integration-model.glb',
            });
            const wrapper = await createWrapper({
                props: {
                    source: mediaEntity,
                },
            });
            await flushPromises();

            // Verify all steps completed
            expect(wrapper.vm.canvas).toBeTruthy();
            expect(wrapper.vm.modelEntity).toEqual(mediaEntity);
            expect(wrapper.vm.isLoading).toBe(false);
            expect(mockQuickView).toHaveBeenCalledWith('https://example.com/integration-model.glb', {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                canvas: expect.any(HTMLCanvasElement),
                displayAxes: true,
                displayGrid: true,
            });
            expect(mockToolbox).toHaveBeenCalled();
            expect(mockEnableTool).toHaveBeenCalledWith('transform');
            expect(mockSelect).toHaveBeenCalled();
        });

        it('should handle media update flow correctly', async () => {
            const mediaEntity = createMediaEntity({
                id: 'update-test-id',
                url: 'https://example.com/original-model.glb',
            });

            // Mock the API response for fetching updated media
            /* eslint-disable @typescript-eslint/no-unsafe-assignment,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-call
            */
            const responses = (globalThis as any).repositoryFactoryMock.responses;
            responses.addResponse(
                /* eslint-enable */
                {
                    method: 'Post',
                    url: '/search/media',
                    status: 200,
                    response: {
                        data: [
                            {
                                id: 'update-test-id',
                                attributes: {
                                    id: 'update-test-id',
                                    url: 'https://example.com/updated-model.glb',
                                },
                                relationships: [],
                            },
                        ],
                    },
                },
            );

            const wrapper = await createWrapper({
                props: {
                    source: mediaEntity,
                },
            });
            await flushPromises();

            // Simulate media update event
            Shopware.Utils.EventBus.emit('sw-media-library-item-updated', 'update-test-id');
            await flushPromises();

            // Verify modelEntity was updated with new URL from API
            // Note: onMediaLibraryItemUpdated only refetches the entity, doesn't reinitialize QuickView
            expect(wrapper.vm.modelEntity).toBeTruthy();
            const updatedEntity = wrapper.vm.modelEntity as EntitySchema.Entity<'media'>;
            expect(updatedEntity?.url).toBe('https://example.com/updated-model.glb');
        });

        it('should not reinitialize when media update is for different id', async () => {
            const mediaEntity = createMediaEntity({
                id: 'update-test-id',
                url: 'https://example.com/original-model.glb',
            });
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const wrapper = await createWrapper({
                props: {
                    source: mediaEntity,
                },
            });
            await flushPromises();

            const initialCallCount = mockQuickView.mock.calls.length;

            // Simulate media update for different id
            Shopware.Utils.EventBus.emit('sw-media-library-item-updated', 'different-media-id');
            await flushPromises();

            expect(mockQuickView.mock.calls).toHaveLength(initialCallCount);
        });

        it('should handle complete edit workflow', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            // Start with translate (default)
            expect(wrapper.vm.currentEditMode).toBe('translate');

            // Switch to rotate
            const buttons = wrapper.findAll('.sw-model-editor-canvas-ui__button');
            await buttons[1].trigger('click');
            expect(wrapper.vm.currentEditMode).toBe('rotate');

            // Switch back to translate
            await buttons[0].trigger('click');
            expect(wrapper.vm.currentEditMode).toBe('translate');
        });
    });
});
