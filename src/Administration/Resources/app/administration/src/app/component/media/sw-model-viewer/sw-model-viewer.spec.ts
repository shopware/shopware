/**
 * @sw-package discovery
 */
import type { QuickViewSettings } from '@shopware-ag/dive/quickview';
import { mount } from '@vue/test-utils';

// Mock QuickView from @shopware-ag/dive/quickview
const mockQuickViewDispose = jest.fn();
const mockQuickView = jest.fn().mockResolvedValue({
    dispose: mockQuickViewDispose,
});
jest.mock('@shopware-ag/dive/quickview', () => ({
    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    QuickView: (...args: QuickViewSettings[]) => mockQuickView(...args),
}));

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
        mockQuickView.mockResolvedValue({
            dispose: mockQuickViewDispose,
        });
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
            expect(wrapper.vm.modelEntity).toBeTruthy();
            const updatedEntity = wrapper.vm.modelEntity as EntitySchema.Entity<'media'>;
            expect(updatedEntity?.url).toBe('https://example.com/updated-model.glb');
        });
    });
});
