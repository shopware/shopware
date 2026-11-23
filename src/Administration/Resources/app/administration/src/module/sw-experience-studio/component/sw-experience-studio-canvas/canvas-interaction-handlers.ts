import type { ResizeDirection, GestureType, CanvasGesture, CanvasFrame } from './canvas.config';
import {
    FRAME_FOCUS_INSET,
    MIN_FRAME_WIDTH,
    MAX_FRAME_WIDTH,
    MIN_FRAME_HEIGHT,
    MAX_FRAME_HEIGHT,
    MIN_CANVAS_ZOOM,
    MAX_CANVAS_ZOOM,
} from './canvas.config';

type CanvasInteractionContext = {
    $refs: Record<string, unknown>;
    $emit: (event: 'frame-viewport-change' | 'viewport-change', ...args: unknown[]) => void;
    $t: (key: string) => string;
    frames: CanvasFrame[];
    visibleFrames: CanvasFrame[];
    frameStates: Record<string, { x: number; y: number; width: number; height: number }>;
    frameX: number;
    frameY: number;
    frameWidth: number;
    frameHeight: number;
    canvasZoom: number;
    canvasPanX: number;
    canvasPanY: number;
    canvasGesture: CanvasGesture | null;
    canvasPointerTarget: HTMLElement | null;
    canvasPointerMoveHandler: ((event: PointerEvent) => void) | null;
    canvasPointerEndHandler: (() => void) | null;
    getWorkspace: () => HTMLElement | null;
    normalizeCanvasZoom: (zoom: number) => number;
    clamp: (value: number, minimum: number, maximum: number) => number;
    clampPosition: (value: number) => number;
    activateFrame: (id: string) => void;
    syncActiveFrameState: () => void;
    saveCanvasPosition: (manuallyPlaced?: boolean) => void;
    startViewportTransition: () => void;
    startCanvasGesture: (event: PointerEvent, type: GestureType, direction?: ResizeDirection | null) => void;
    stopCanvasGesture: () => void;
    onCanvasPointerMove: (event: PointerEvent) => void;
    applyFrameResize: (gesture: CanvasGesture, deltaX: number, deltaY: number) => void;
};

/** @private @sw-package discovery */
export const canvasInteractionHandlers = {
    onBackgroundPointerDown(this: CanvasInteractionContext, event: PointerEvent): void {
        const background = this.$refs.background as HTMLElement | undefined;

        if (event.button !== 0 || event.target !== background) {
            return;
        }

        this.startCanvasGesture(event, 'pan');
    },

    onCanvasWheel(this: CanvasInteractionContext, event: WheelEvent): void {
        const background = this.$refs.background as HTMLElement | undefined;

        if (event.target !== background) {
            return;
        }

        event.preventDefault();

        const workspace = this.getWorkspace();

        if (!workspace) {
            return;
        }

        const previousZoom = this.canvasZoom;
        const nextZoom = this.normalizeCanvasZoom(previousZoom * Math.exp(-event.deltaY * 0.001));

        if (nextZoom === previousZoom) {
            return;
        }

        const workspaceBounds = workspace.getBoundingClientRect();
        const zoomRatio = nextZoom / previousZoom;
        const pointerX = event.clientX - workspaceBounds.left - workspaceBounds.width / 2;
        const pointerY = event.clientY - workspaceBounds.top - workspaceBounds.height / 2;

        this.canvasPanX = pointerX - (pointerX - this.canvasPanX) * zoomRatio;
        this.canvasPanY = pointerY - (pointerY - this.canvasPanY) * zoomRatio;
        this.canvasZoom = nextZoom;
    },

    onFrameMoveStart(this: CanvasInteractionContext, event: PointerEvent): void {
        const frameId = (event.currentTarget as HTMLElement | null)
            ?.closest('[data-frame-id]')
            ?.getAttribute('data-frame-id');

        if (frameId) {
            this.activateFrame(frameId);
        }

        this.startCanvasGesture(event, 'move');
    },

    onFrameResizeStart(this: CanvasInteractionContext, event: PointerEvent, direction: ResizeDirection): void {
        if (event.button !== 0) {
            return;
        }

        const frameId = (event.currentTarget as HTMLElement | null)
            ?.closest('[data-frame-id]')
            ?.getAttribute('data-frame-id');

        if (frameId) {
            this.activateFrame(frameId);
            this.$emit('frame-viewport-change', frameId, 'custom');
        } else {
            this.$emit('viewport-change', 'custom');
        }
        this.startCanvasGesture(event, 'resize', direction);
    },

    startCanvasGesture(
        this: CanvasInteractionContext,
        event: PointerEvent,
        type: GestureType,
        direction: ResizeDirection | null = null,
    ): void {
        if (event.button !== 0) {
            return;
        }

        event.preventDefault();

        const target = event.currentTarget as HTMLElement | null;

        if (target?.setPointerCapture) {
            target.setPointerCapture(event.pointerId);
        }

        this.canvasPointerTarget = target;
        this.canvasGesture = {
            type,
            visibleFrameIds: this.visibleFrames.map((frame) => frame.id),
            direction,
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startClientY: event.clientY,
            startFrameX: this.frameX,
            startFrameY: this.frameY,
            startFrameWidth: this.frameWidth,
            startFrameHeight: this.frameHeight,
            startPanX: this.canvasPanX,
            startPanY: this.canvasPanY,
        };
        this.canvasPointerMoveHandler = (moveEvent: PointerEvent): void => this.onCanvasPointerMove(moveEvent);
        this.canvasPointerEndHandler = (): void => this.stopCanvasGesture();

        document.addEventListener('pointermove', this.canvasPointerMoveHandler);
        document.addEventListener('pointerup', this.canvasPointerEndHandler, { once: true });
        document.addEventListener('pointercancel', this.canvasPointerEndHandler);
        window.addEventListener('blur', this.canvasPointerEndHandler);
    },

    onCanvasPointerMove(this: CanvasInteractionContext, event: PointerEvent): void {
        const gesture = this.canvasGesture;

        if (!gesture || gesture.pointerId !== event.pointerId) {
            return;
        }

        const deltaX = (event.clientX - gesture.startClientX) / this.canvasZoom;
        const deltaY = (event.clientY - gesture.startClientY) / this.canvasZoom;

        if (gesture.type === 'pan') {
            this.canvasPanX = gesture.startPanX + (event.clientX - gesture.startClientX);
            this.canvasPanY = gesture.startPanY + (event.clientY - gesture.startClientY);

            return;
        }

        if (gesture.type === 'move') {
            this.frameX = this.clampPosition(gesture.startFrameX + deltaX);
            this.frameY = this.clampPosition(gesture.startFrameY + deltaY);
            this.syncActiveFrameState();

            return;
        }

        this.applyFrameResize(gesture, deltaX, deltaY);
    },

    applyFrameResize(this: CanvasInteractionContext, gesture: CanvasGesture, deltaX: number, deltaY: number): void {
        const direction = gesture.direction;

        if (!direction) {
            return;
        }

        let nextWidth = gesture.startFrameWidth;
        let nextHeight = gesture.startFrameHeight;
        let nextX = gesture.startFrameX;
        let nextY = gesture.startFrameY;

        if (direction.includes('e')) {
            nextWidth = this.clamp(gesture.startFrameWidth + deltaX, MIN_FRAME_WIDTH, MAX_FRAME_WIDTH);
            nextX += (nextWidth - gesture.startFrameWidth) / 2;
        } else if (direction.includes('w')) {
            nextWidth = this.clamp(gesture.startFrameWidth - deltaX, MIN_FRAME_WIDTH, MAX_FRAME_WIDTH);
            nextX -= (nextWidth - gesture.startFrameWidth) / 2;
        }

        if (direction.includes('s')) {
            nextHeight = this.clamp(gesture.startFrameHeight + deltaY, MIN_FRAME_HEIGHT, MAX_FRAME_HEIGHT);
            nextY += (nextHeight - gesture.startFrameHeight) / 2;
        } else if (direction.includes('n')) {
            nextHeight = this.clamp(gesture.startFrameHeight - deltaY, MIN_FRAME_HEIGHT, MAX_FRAME_HEIGHT);
            nextY -= (nextHeight - gesture.startFrameHeight) / 2;
        }

        this.frameWidth = nextWidth;
        this.frameHeight = nextHeight;
        this.frameX = this.clampPosition(nextX);
        this.frameY = this.clampPosition(nextY);
        this.syncActiveFrameState();
    },

    stopCanvasGesture(this: CanvasInteractionContext): void {
        const gesture = this.canvasGesture;

        if (this.canvasPointerMoveHandler) {
            document.removeEventListener('pointermove', this.canvasPointerMoveHandler);
        }

        if (this.canvasPointerEndHandler) {
            document.removeEventListener('pointerup', this.canvasPointerEndHandler);
            document.removeEventListener('pointercancel', this.canvasPointerEndHandler);
            window.removeEventListener('blur', this.canvasPointerEndHandler);
        }

        if (this.canvasPointerTarget && gesture && this.canvasPointerTarget.hasPointerCapture?.(gesture.pointerId)) {
            this.canvasPointerTarget.releasePointerCapture(gesture.pointerId);
        }

        if (gesture?.type === 'move' || gesture?.type === 'resize') {
            this.saveCanvasPosition(gesture.type === 'move');
        }

        this.canvasPointerMoveHandler = null;
        this.canvasPointerEndHandler = null;
        this.canvasPointerTarget = null;
        this.canvasGesture = null;
    },

    fitCanvasToFrame(this: CanvasInteractionContext): void {
        const workspace = this.getWorkspace();

        if (!workspace) {
            return;
        }

        const availableWidth = Math.max(1, workspace.clientWidth - 96);
        const availableHeight = Math.max(1, workspace.clientHeight - 96);
        const zoom = Math.min(1, availableWidth / this.frameWidth, availableHeight / this.frameHeight);

        this.canvasZoom = this.clamp(zoom, MIN_CANVAS_ZOOM, MAX_CANVAS_ZOOM);
        this.canvasPanX = -this.frameX * this.canvasZoom;
        this.canvasPanY = -this.frameY * this.canvasZoom;
    },

    focusFrame(this: CanvasInteractionContext, layoutId: string): void {
        const state = this.frameStates[layoutId];
        const workspace = this.getWorkspace();

        if (!state || !workspace) {
            return;
        }

        this.activateFrame(layoutId);
        this.startViewportTransition();
        this.canvasZoom = 1;
        this.canvasPanX = FRAME_FOCUS_INSET + (state.width - workspace.clientWidth) / 2 - state.x;
        this.canvasPanY = FRAME_FOCUS_INSET + (state.height - workspace.clientHeight) / 2 - state.y;
    },

    fitCanvasToFrames(this: CanvasInteractionContext): void {
        const workspace = this.getWorkspace();
        const states = this.frames
            .map((frame) => this.frameStates[frame.id])
            .filter((state): state is { x: number; y: number; width: number; height: number } => Boolean(state));

        if (!workspace || states.length === 0) {
            return;
        }

        const bounds = states.reduce(
            (currentBounds, state) => ({
                minX: Math.min(currentBounds.minX, state.x - state.width / 2),
                maxX: Math.max(currentBounds.maxX, state.x + state.width / 2),
                minY: Math.min(currentBounds.minY, state.y - state.height / 2),
                maxY: Math.max(currentBounds.maxY, state.y + state.height / 2),
            }),
            {
                minX: Number.POSITIVE_INFINITY,
                maxX: Number.NEGATIVE_INFINITY,
                minY: Number.POSITIVE_INFINITY,
                maxY: Number.NEGATIVE_INFINITY,
            },
        );
        const { minX, maxX, minY, maxY } = bounds;
        const contentWidth = Math.max(1, maxX - minX);
        const contentHeight = Math.max(1, maxY - minY);
        const availableWidth = Math.max(1, workspace.clientWidth - 96);
        const availableHeight = Math.max(1, workspace.clientHeight - 96);

        this.startViewportTransition();
        this.canvasZoom = this.clamp(
            Math.min(1, availableWidth / contentWidth, availableHeight / contentHeight),
            MIN_CANVAS_ZOOM,
            MAX_CANVAS_ZOOM,
        );
        this.canvasPanX = -((minX + maxX) / 2) * this.canvasZoom;
        this.canvasPanY = -((minY + maxY) / 2) * this.canvasZoom;
    },

    resetCanvasZoom(this: CanvasInteractionContext): void {
        this.startViewportTransition();
        this.canvasZoom = 1;
        this.canvasPanX = -this.frameX;
        this.canvasPanY = -this.frameY;
    },

    changeCanvasZoom(this: CanvasInteractionContext, delta: number): void {
        const previousZoom = this.canvasZoom;
        const nextZoom = this.normalizeCanvasZoom(Math.max(previousZoom / 2, previousZoom + delta));
        const zoomRatio = nextZoom / previousZoom;

        this.canvasZoom = nextZoom;
        this.canvasPanX *= zoomRatio;
        this.canvasPanY *= zoomRatio;
    },

    onFrameMoveKeydown(this: CanvasInteractionContext, event: KeyboardEvent): void {
        const frameId = (event.currentTarget as HTMLElement | null)
            ?.closest('[data-frame-id]')
            ?.getAttribute('data-frame-id');

        if (frameId) {
            this.activateFrame(frameId);
        }

        const step = event.shiftKey ? 50 : 10;
        let nextX = this.frameX;
        let nextY = this.frameY;

        if (event.key === 'ArrowLeft') {
            nextX -= step;
        } else if (event.key === 'ArrowRight') {
            nextX += step;
        } else if (event.key === 'ArrowUp') {
            nextY -= step;
        } else if (event.key === 'ArrowDown') {
            nextY += step;
        } else {
            return;
        }

        event.preventDefault();
        this.frameX = this.clampPosition(nextX);
        this.frameY = this.clampPosition(nextY);
        this.saveCanvasPosition(true);
    },

    resizeHandleLabel(this: CanvasInteractionContext, direction: ResizeDirection): string {
        const labels: Record<ResizeDirection, string> = {
            n: this.$t('sw-experience-studio.detail.canvas.resizeNorth'),
            ne: this.$t('sw-experience-studio.detail.canvas.resizeNorthEast'),
            e: this.$t('sw-experience-studio.detail.canvas.resizeEast'),
            se: this.$t('sw-experience-studio.detail.canvas.resizeSouthEast'),
            s: this.$t('sw-experience-studio.detail.canvas.resizeSouth'),
            sw: this.$t('sw-experience-studio.detail.canvas.resizeSouthWest'),
            w: this.$t('sw-experience-studio.detail.canvas.resizeWest'),
            nw: this.$t('sw-experience-studio.detail.canvas.resizeNorthWest'),
        };

        return labels[direction];
    },
};

/** @private @sw-package discovery */
export function isCanvasFrameVisible(
    state: { x: number; y: number; width: number; height: number } | undefined,
    bounds: DOMRect | undefined,
    camera: { panX: number; panY: number; zoom: number },
): boolean {
    if (!bounds || !state) {
        return true;
    }

    const { panX, panY, zoom } = camera;
    const centerX = bounds.width / 2 + panX + state.x * zoom;
    const centerY = bounds.height / 2 + panY + state.y * zoom;
    const frameWidth = state.width * zoom;
    const frameHeight = state.height * zoom;

    return (
        centerX + frameWidth / 2 >= 0 &&
        centerX - frameWidth / 2 <= bounds.width &&
        centerY + frameHeight / 2 >= 0 &&
        centerY - frameHeight / 2 <= bounds.height
    );
}
