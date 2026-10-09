import canvasComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-canvas', () => {
    const methods = (canvasComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (canvasComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;
    const watchers = (canvasComponent as unknown as { watch: Record<string, (...args: unknown[]) => unknown> }).watch;

    it('moves and scales the dotted background with the canvas', () => {
        const backgroundStyle = computed.backgroundStyle.call({
            canvasZoom: 0.75,
            canvasPanX: 32,
            canvasPanY: -18,
        });

        expect(backgroundStyle).toEqual({
            backgroundSize: '18px 18px',
            backgroundPosition: 'calc(50% + 32px) calc(50% - 18px)',
        });
    });

    it('renders only frames intersecting the visible canvas area', () => {
        const visibleFrame = { id: 'visible' };
        const offscreenFrame = { id: 'offscreen' };
        const workspace = {
            getBoundingClientRect: () => ({ width: 800, height: 600 }),
        };
        const result = computed.visibleFrames.call({
            $refs: { workspace },
            frames: [
                visibleFrame,
                offscreenFrame,
            ],
            frameStates: {
                visible: { x: 0, y: 0, width: 300, height: 400 },
                offscreen: { x: 1000, y: 0, width: 300, height: 400 },
            },
            canvasPanX: 0,
            canvasPanY: 0,
            canvasZoom: 1,
        });

        expect(result).toEqual([visibleFrame]);
    });

    it('activates a changed layout without moving the canvas to its frame', () => {
        const activateFrame = jest.fn();
        const fitCanvasToFrame = jest.fn();
        const vm = {
            layoutId: 'layout-2',
            frameStates: {
                'layout-2': { x: 1200, y: 400, width: 1480, height: 640 },
            },
            activateFrame,
            fitCanvasToFrame,
        };

        watchers.layoutId.call(vm);

        expect(activateFrame).toHaveBeenCalledWith('layout-2', false);
        expect(fitCanvasToFrame).not.toHaveBeenCalled();
    });

    it('spreads legacy layouts that were all saved at the single-frame origin', () => {
        localStorage.clear();
        localStorage.setItem('sw-experience-studio-canvas-position.user-1.layout-1', JSON.stringify({ x: 0, y: 0 }));
        localStorage.setItem('sw-experience-studio-canvas-position.user-1.layout-2', JSON.stringify({ x: 0, y: 0 }));
        const vm = {
            frames: [
                { id: 'layout-1', viewport: 'desktop', isActive: true },
                { id: 'layout-2', viewport: 'desktop', isActive: false },
            ],
            frameStates: {},
            activeFrameId: '',
            frameX: 0,
            frameY: 0,
            frameWidth: 1480,
            frameHeight: 640,
            getCurrentUserId: () => 'user-1',
            getDefaultFrameHeight: () => 640,
            activateFrame: methods.activateFrame,
        };

        methods.initializeFrameStates.call(vm);

        const frameStates = vm.frameStates as Record<string, { x: number }>;

        expect(frameStates['layout-1'].x).toBe(0);
        expect(frameStates['layout-2'].x).toBe(1540);
    });

    it('places a newly opened layout 60px to the right of the active frame on the same row', () => {
        localStorage.clear();
        const vm = {
            frames: [
                { id: 'layout-active', viewport: 'desktop', isActive: true },
                { id: 'layout-new', viewport: 'desktop', isActive: false },
            ],
            frameStates: {
                'layout-active': { x: 500, y: -300, width: 1480, height: 640 },
            },
            activeFrameId: 'layout-active',
            frameX: 500,
            frameY: -300,
            frameWidth: 1480,
            frameHeight: 640,
            getCurrentUserId: () => 'user-1',
            getDefaultFrameHeight: () => 640,
            activateFrame: methods.activateFrame,
        };

        methods.initializeFrameStates.call(vm);

        const frameStates = vm.frameStates as Record<string, { x: number; y: number }>;

        expect(frameStates['layout-new']).toEqual({ x: 2040, y: -300, width: 1480, height: 640 });
    });

    it('places a newly opened layout after occupied space in the active frame row', () => {
        localStorage.clear();
        const vm = {
            frames: [
                { id: 'layout-active', viewport: 'desktop', isActive: true },
                { id: 'layout-existing', viewport: 'desktop', isActive: false },
                { id: 'layout-new', viewport: 'desktop', isActive: false },
            ],
            frameStates: {
                'layout-active': { x: 0, y: 0, width: 1480, height: 640 },
                'layout-existing': { x: 1560, y: 0, width: 1480, height: 640 },
            },
            activeFrameId: 'layout-active',
            frameX: 0,
            frameY: 0,
            getCurrentUserId: () => 'user-1',
            getDefaultFrameHeight: () => 640,
            activateFrame: methods.activateFrame,
        };

        methods.initializeFrameStates.call(vm);

        const frameStates = vm.frameStates as Record<string, { x: number; y: number; width: number; height: number }>;

        expect(frameStates['layout-new']).toEqual({ x: 3100, y: 0, width: 1480, height: 640 });
    });

    it('fits multiple open frames into the canvas after adding a layout', () => {
        const workspace = { clientWidth: 1600, clientHeight: 900 } as HTMLElement;
        const vm = {
            frames: [
                { id: 'layout-1' },
                { id: 'layout-2' },
            ],
            frameStates: {
                'layout-1': { x: 0, y: 0, width: 1480, height: 640 },
                'layout-2': { x: 1560, y: 0, width: 1480, height: 640 },
            },
            canvasPanX: 0,
            canvasPanY: 0,
            canvasZoom: 1,
            getWorkspace: () => workspace,
            startViewportTransition: jest.fn(),
            clamp: methods.clamp,
        };

        methods.fitCanvasToFrames.call(vm);

        const visibleFrames = computed.visibleFrames.call({
            $refs: { workspace: { getBoundingClientRect: () => ({ width: 1600, height: 900 }) } },
            frames: vm.frames,
            frameStates: vm.frameStates,
            canvasPanX: vm.canvasPanX,
            canvasPanY: vm.canvasPanY,
            canvasZoom: vm.canvasZoom,
        });

        expect(visibleFrames).toHaveLength(2);
        expect(vm.startViewportTransition).toHaveBeenCalledTimes(1);
    });

    it.each([
        [
            'mobile',
            375,
        ],
        [
            'tablet-landscape',
            768,
        ],
        [
            'desktop',
            1480,
        ],
    ] as const)('sets the %s viewport width preset', (viewport, width) => {
        const emit = jest.fn();
        const vm = {
            frameWidth: 0,
            frameHeight: 0,
            canvasZoom: 0.75,
            canvasPanX: 120,
            canvasPanY: -80,
            getDefaultFrameHeight: () => 720,
            startViewportTransition: jest.fn(),
            applyViewportPreset: methods.applyViewportPreset,
            $emit: emit,
        };

        methods.onViewportChange.call(vm, viewport);

        expect(vm.frameWidth).toBe(width);
        expect(vm.frameHeight).toBe(720);
        expect(vm.canvasZoom).toBe(0.75);
        expect(vm.canvasPanX).toBe(120);
        expect(vm.canvasPanY).toBe(-80);
        expect(emit).toHaveBeenCalledWith('viewport-change', viewport);
    });

    it('marks manual frame resizing as a custom viewport', () => {
        const startCanvasGesture = jest.fn();
        const emit = jest.fn();
        const event = { button: 0 } as PointerEvent;

        methods.onFrameResizeStart.call(
            {
                $emit: emit,
                startCanvasGesture,
            },
            event,
            'se',
        );

        expect(emit).toHaveBeenCalledWith('viewport-change', 'custom');
        expect(startCanvasGesture).toHaveBeenCalledWith(event, 'resize', 'se');
    });

    it('zooms around the pointer when scrolling over the free canvas background', () => {
        const background = document.createElement('div');
        const workspace = {
            getBoundingClientRect: () => ({
                left: 100,
                top: 50,
                width: 800,
                height: 600,
            }),
        } as unknown as HTMLElement;
        const event = {
            target: background,
            deltaY: -100,
            clientX: 300,
            clientY: 200,
            preventDefault: jest.fn(),
        } as unknown as WheelEvent;
        const vm = {
            $refs: { background },
            canvasZoom: 1,
            canvasPanX: 0,
            canvasPanY: 0,
            getWorkspace: () => workspace,
            clamp: methods.clamp,
            normalizeCanvasZoom: methods.normalizeCanvasZoom,
        };

        methods.onCanvasWheel.call(vm, event);

        const zoom = Math.exp(0.1);
        expect(Reflect.get(event, 'preventDefault')).toHaveBeenCalled();
        expect(vm.canvasZoom).toBeCloseTo(zoom);
        expect(vm.canvasPanX).toBeCloseTo(-200 - -200 * zoom);
        expect(vm.canvasPanY).toBeCloseTo(-150 - -150 * zoom);
    });

    it('snaps wheel zoom back to 100% when it comes within one percentage point', () => {
        const background = document.createElement('div');
        const workspace = {
            getBoundingClientRect: () => ({
                left: 0,
                top: 0,
                width: 800,
                height: 600,
            }),
        } as unknown as HTMLElement;
        const vm = {
            $refs: { background },
            canvasZoom: 0.99,
            canvasPanX: 0,
            canvasPanY: 0,
            getWorkspace: () => workspace,
            clamp: methods.clamp,
            normalizeCanvasZoom: methods.normalizeCanvasZoom,
        };

        methods.onCanvasWheel.call(vm, {
            target: background,
            deltaY: -1,
            clientX: 400,
            clientY: 300,
            preventDefault: jest.fn(),
        } as unknown as WheelEvent);

        expect(vm.canvasZoom).toBe(1);
    });

    it('resets the canvas zoom to 100% and centers the frame', () => {
        const vm = {
            frameX: 20,
            frameY: -10,
            canvasZoom: 0.75,
            canvasPanX: 50,
            canvasPanY: -50,
        };

        methods.resetCanvasZoom.call(vm);

        expect(vm.canvasZoom).toBe(1);
        expect(vm.canvasPanX).toBe(-20);
        expect(vm.canvasPanY).toBe(10);
    });

    it('fits one frame into the available canvas area and centers it', () => {
        const workspace = { clientWidth: 1200, clientHeight: 800 } as HTMLElement;
        const vm = {
            frameX: 100,
            frameY: -40,
            frameWidth: 1480,
            frameHeight: 640,
            canvasZoom: 1,
            canvasPanX: 0,
            canvasPanY: 0,
            getWorkspace: () => workspace,
            clamp: methods.clamp,
        };

        methods.fitCanvasToFrame.call(vm);

        const expectedZoom = (workspace.clientWidth - 96) / vm.frameWidth;
        expect(vm.canvasZoom).toBeCloseTo(expectedZoom);
        expect(vm.canvasPanX).toBeCloseTo(-vm.frameX * expectedZoom);
        expect(vm.canvasPanY).toBeCloseTo(-vm.frameY * expectedZoom);
    });

    it('focuses a frame by activating it and centering it at the current zoom', () => {
        const activateFrame = jest.fn();
        const startViewportTransition = jest.fn();
        const vm = {
            frameStates: {
                'layout-2': { x: 400, y: -200, width: 1480, height: 640 },
            },
            canvasZoom: 0.5,
            canvasPanX: 0,
            canvasPanY: 0,
            activateFrame,
            startViewportTransition,
        };

        methods.focusFrame.call(vm, 'layout-2');

        expect(activateFrame).toHaveBeenCalledWith('layout-2');
        expect(startViewportTransition).toHaveBeenCalledTimes(1);
        expect(vm.canvasPanX).toBe(-200);
        expect(vm.canvasPanY).toBe(100);
        expect(vm.canvasZoom).toBe(0.5);
    });

    it('does not zoom or prevent scrolling when the wheel event is outside the free canvas background', () => {
        const background = document.createElement('div');
        const preventDefault = jest.fn();
        const event = {
            target: document.createElement('section'),
            deltaY: -100,
            preventDefault,
        } as unknown as WheelEvent;
        const vm = {
            $refs: { background },
            canvasZoom: 1,
            canvasPanX: 0,
            canvasPanY: 0,
        };

        methods.onCanvasWheel.call(vm, event);

        expect(preventDefault).not.toHaveBeenCalled();
        expect(vm.canvasZoom).toBe(1);
        expect(vm.canvasPanX).toBe(0);
        expect(vm.canvasPanY).toBe(0);
    });

    it('resizes the frame while keeping the opposite edge anchored', () => {
        const vm = {
            frameX: 0,
            frameY: 0,
            frameWidth: 768,
            frameHeight: 640,
            clamp: methods.clamp,
            clampPosition: methods.clampPosition,
            syncActiveFrameState: methods.syncActiveFrameState,
        };
        const gesture = {
            type: 'resize',
            direction: 'e',
            pointerId: 1,
            startClientX: 0,
            startClientY: 0,
            startFrameX: 0,
            startFrameY: 0,
            startFrameWidth: 768,
            startFrameHeight: 640,
            startPanX: 0,
            startPanY: 0,
        };

        methods.applyFrameResize.call(vm, gesture, 100, 0);

        expect(vm.frameWidth).toBe(868);
        expect(vm.frameX).toBe(50);
        expect(vm.frameHeight).toBe(640);
    });

    it('converts pointer movement into canvas coordinates when moving the frame', () => {
        const vm = {
            canvasZoom: 0.5,
            canvasGesture: {
                type: 'move',
                direction: null,
                pointerId: 7,
                startClientX: 100,
                startClientY: 200,
                startFrameX: 20,
                startFrameY: -10,
                startFrameWidth: 1480,
                startFrameHeight: 640,
                startPanX: 0,
                startPanY: 0,
            },
            frameX: 20,
            frameY: -10,
            activeFrameId: 'layout-active',
            frameWidth: 1480,
            frameHeight: 640,
            frameStates: {
                'layout-active': { x: 20, y: -10, width: 1480, height: 640 },
            },
            clampPosition: methods.clampPosition,
            syncActiveFrameState: methods.syncActiveFrameState,
        };

        methods.onCanvasPointerMove.call(vm, {
            clientX: 130,
            clientY: 250,
            pointerId: 7,
        } as PointerEvent);

        expect(vm.frameX).toBe(80);
        expect(vm.frameY).toBe(90);
        expect(vm.frameStates['layout-active']).toEqual({ x: 80, y: 90, width: 1480, height: 640 });
    });

    it('toggles the floating panels', () => {
        const vm = {
            activePanel: 'structure',
            isSettingsOpen: false,
            $emit: jest.fn(),
            openSettingsPanel: methods.openSettingsPanel,
            closeSettingsPanel: methods.closeSettingsPanel,
        };

        methods.togglePanel.call(vm, 'settings');
        expect(vm.activePanel).toBe('structure');
        expect(vm.isSettingsOpen).toBe(true);

        methods.togglePanel.call(vm, 'settings');
        expect(vm.activePanel).toBe('structure');
        expect(vm.isSettingsOpen).toBe(false);
        expect(vm.$emit).toHaveBeenCalledWith('settings-close');
    });

    it('opens the settings panel when requested by the preview selection', () => {
        const vm = {
            isSettingsOpen: false,
        };

        methods.openSettingsPanel.call(vm);

        expect(vm.isSettingsOpen).toBe(true);
    });

    it('closes the settings panel without closing the structure panel', () => {
        const emit = jest.fn();
        const vm = {
            activePanel: 'structure',
            isSettingsOpen: true,
            $emit: emit,
        };

        methods.closeSettingsPanel.call(vm);

        expect(vm.activePanel).toBe('structure');
        expect(vm.isSettingsOpen).toBe(false);
        expect(emit).toHaveBeenCalledWith('settings-close');
    });
});
