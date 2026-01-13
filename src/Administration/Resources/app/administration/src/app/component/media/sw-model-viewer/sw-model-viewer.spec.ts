/**
 * @sw-package discovery
 */
import type { QuickViewSettings } from '@shopware-ag/dive/quickview';
import { mount } from '@vue/test-utils';

// Mock QuickView from @shopware-ag/dive/quickview
const mockQuickView = jest.fn().mockResolvedValue({});
jest.mock('@shopware-ag/dive/quickview', () => ({
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    QuickView: (...args: QuickViewSettings[]) => mockQuickView(...args),
}));

// Mock ResizeObserver
const mockResizeObserverDisconnect = jest.fn();
const mockResizeObserverObserve = jest.fn();
const mockResizeObserverCallback = jest.fn();

class MockResizeObserver {
    callback: ResizeObserverCallback;

    constructor(callback: ResizeObserverCallback) {
        this.callback = callback;
        mockResizeObserverCallback.mockImplementation((entries: ResizeObserverEntry[]) => {
            this.callback(entries, this);
        });
    }

    observe = mockResizeObserverObserve;

    disconnect = mockResizeObserverDisconnect;

    unobserve = jest.fn();
}

global.ResizeObserver = MockResizeObserver as unknown as typeof ResizeObserver;

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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function createWrapper(componentConfig: any = {}) {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    return mount(await wrapTestComponent('sw-model-viewer', { sync: true }), {
        props: {
            source: createMediaEntity(),
        },
        global: {
            stubs: {
                'mt-loader': {
                    template: '<div class="mt-loader"></div>',
                },
            },
        },
        ...componentConfig,
    });
}

describe('src/app/component/media/sw-model-viewer', () => {
    // Media entity factory

    beforeEach(() => {
        jest.clearAllMocks();
        mockQuickView.mockResolvedValue({});
        mockResizeObserverDisconnect.mockClear();
        mockResizeObserverObserve.mockClear();
        mockResizeObserverCallback.mockClear();
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
    });

    describe('Lifecycle Hooks', () => {
        it('should find canvas element and initialize QuickView on mount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvas = wrapper.find('.sw-model-viewer-canvas');
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
        it('should initialize QuickView with correct parameters', async () => {
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
            });
        });

        it('should set isLoading to false after QuickView completes', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.isLoading).toBe(false);
        });

        it('should return early if canvas is null', async () => {
            const wrapper = await createWrapper();
            await wrapper.setData({ canvas: null });
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView();
            await flushPromises();

            // QuickView should not be called if canvas is null
            const callCountBefore = mockQuickView.mock.calls.length;
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView();
            await flushPromises();

            expect(mockQuickView.mock.calls).toHaveLength(callCountBefore);
            expect(wrapper.vm.isLoading).toBe(false);
        });

        it('should return early if modelEntity is null', async () => {
            const wrapper = await createWrapper();
            await wrapper.setData({ modelEntity: null });
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView();
            await flushPromises();

            const callCountBefore = mockQuickView.mock.calls.length;
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            await (wrapper.vm as any).initializeQuickView();
            await flushPromises();

            expect(mockQuickView.mock.calls).toHaveLength(callCountBefore);
            expect(wrapper.vm.isLoading).toBe(false);
        });

        it('should return early if modelEntity.url is missing', async () => {
            const mediaEntity = createMediaEntity({ url: undefined });
            const wrapper = await createWrapper({
                props: {
                    source: mediaEntity,
                },
            });
            await flushPromises();

            expect(mockQuickView.mock.calls).toHaveLength(0);
            expect(wrapper.vm.isLoading).toBe(false);
        });

        it('should handle QuickView errors gracefully', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
            mockQuickView.mockRejectedValueOnce(new Error('QuickView failed'));

            const wrapper = await createWrapper();
            await flushPromises();

            // Component should still exist and isLoading should be false
            expect(wrapper.exists()).toBe(true);
            expect(wrapper.vm.isLoading).toBe(false);

            consoleErrorSpy.mockRestore();
        });

        it('should set isLoading to false even when QuickView fails', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
            mockQuickView.mockRejectedValueOnce(new Error('QuickView failed'));

            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.isLoading).toBe(false);

            consoleErrorSpy.mockRestore();
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

        it('should call initializeQuickView when source prop changes', async () => {
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

            expect(mockQuickView.mock.calls.length).toBeGreaterThan(initialCallCount);
        });

        it('should handle source prop change errors gracefully', async () => {
            const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
            mockQuickView.mockRejectedValueOnce(new Error('QuickView failed'));

            const wrapper = await createWrapper();
            await flushPromises();

            const newMediaEntity = createMediaEntity({
                id: 'new-media-id',
                url: 'https://example.com/new-model.glb',
            });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-explicit-any
            await wrapper.setProps({ source: newMediaEntity } as any);
            await flushPromises();

            expect(wrapper.exists()).toBe(true);
            expect(consoleErrorSpy).toHaveBeenCalled();

            consoleErrorSpy.mockRestore();
        });
    });

    describe('ResizeObserver', () => {
        it('should initialize ResizeObserver on mount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.resizeObserver).toBeTruthy();
            expect(wrapper.vm.canvasWrapper).toBeTruthy();
            expect(mockResizeObserverObserve).toHaveBeenCalled();
        });

        it('should observe the canvas wrapper element', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvasWrapper = wrapper.find('.sw-model-viewer-canvas-wrapper').element;
            expect(mockResizeObserverObserve).toHaveBeenCalledWith(canvasWrapper);
        });

        it('should set height equal to width when resize is observed', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvasWrapper = wrapper.find('.sw-model-viewer-canvas-wrapper').element as HTMLElement;

            // Simulate a resize event
            const mockEntry = {
                target: canvasWrapper,
                contentRect: { width: 400 },
            } as unknown as ResizeObserverEntry;

            mockResizeObserverCallback([mockEntry]);

            expect(canvasWrapper.style.height).toBe('400px');
        });

        it('should handle multiple resize entries', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvasWrapper = wrapper.find('.sw-model-viewer-canvas-wrapper').element as HTMLElement;

            // Simulate resize with different width
            const mockEntry1 = {
                target: canvasWrapper,
                contentRect: { width: 200 },
            } as unknown as ResizeObserverEntry;

            mockResizeObserverCallback([mockEntry1]);
            expect(canvasWrapper.style.height).toBe('200px');

            // Simulate another resize
            const mockEntry2 = {
                target: canvasWrapper,
                contentRect: { width: 600 },
            } as unknown as ResizeObserverEntry;

            mockResizeObserverCallback([mockEntry2]);
            expect(canvasWrapper.style.height).toBe('600px');
        });

        it('should not initialize ResizeObserver if canvasWrapper is null', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            // Reset mocks and manually test initResizeObserver with null wrapper
            mockResizeObserverObserve.mockClear();
            await wrapper.setData({ canvasWrapper: null, resizeObserver: null });

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).initResizeObserver();

            expect(wrapper.vm.resizeObserver).toBeNull();
        });

        it('should disconnect ResizeObserver on unmount', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            expect(wrapper.vm.resizeObserver).toBeTruthy();

            wrapper.unmount();

            expect(mockResizeObserverDisconnect).toHaveBeenCalled();
        });

        it('should set resizeObserver to null after disconnect', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const resizeObserver = wrapper.vm.resizeObserver;
            expect(resizeObserver).toBeTruthy();

            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access
            */
            (wrapper.vm as any).beforeUnmountedComponent();

            expect(wrapper.vm.resizeObserver).toBeNull();
        });

        it('should handle beforeUnmountedComponent when resizeObserver is null', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            await wrapper.setData({ resizeObserver: null });

            // Should not throw
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-call,
                @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-return
            */
            expect(() => (wrapper.vm as any).beforeUnmountedComponent()).not.toThrow();
        });
    });

    describe('Template Rendering', () => {
        it('should render canvas element with correct class', async () => {
            const wrapper = await createWrapper();
            await flushPromises();

            const canvas = wrapper.find('.sw-model-viewer-canvas');
            expect(canvas.exists()).toBe(true);
            expect(canvas.classes()).toContain('sw-model-viewer-canvas');
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

            expect(wrapper.find('.sw-model-viewer').exists()).toBe(true);
            expect(wrapper.find('.sw-model-viewer-canvas-wrapper').exists()).toBe(true);
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
            });
        });

        it('should handle media update flow correctly', async () => {
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

            // Simulate media update
            Shopware.Utils.EventBus.emit('sw-media-library-item-updated', 'update-test-id');
            await flushPromises();

            expect(mockQuickView.mock.calls.length).toBeGreaterThan(initialCallCount);
        });
    });
});
