import canvasComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-canvas', () => {
    const methods = (canvasComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
    const computed = (canvasComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

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
        const fitCanvasToFrame = jest.fn();
        const emit = jest.fn();
        const vm = {
            frameWidth: 0,
            frameHeight: 0,
            getDefaultFrameHeight: () => 720,
            startViewportTransition: jest.fn(),
            applyViewportPreset: methods.applyViewportPreset,
            fitCanvasToFrame,
            $emit: emit,
            $nextTick: (callback: () => void) => callback(),
        };

        methods.onViewportChange.call(vm, viewport);

        expect(vm.frameWidth).toBe(width);
        expect(vm.frameHeight).toBe(720);
        expect(emit).toHaveBeenCalledWith('viewport-change', viewport);
        expect(fitCanvasToFrame).toHaveBeenCalledTimes(1);
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
            clampPosition: methods.clampPosition,
        };

        methods.onCanvasPointerMove.call(vm, {
            clientX: 130,
            clientY: 250,
            pointerId: 7,
        } as PointerEvent);

        expect(vm.frameX).toBe(80);
        expect(vm.frameY).toBe(90);
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
