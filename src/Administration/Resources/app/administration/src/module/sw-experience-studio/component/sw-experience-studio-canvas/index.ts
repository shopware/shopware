import { loadExperienceStudioCanvasPosition, saveExperienceStudioCanvasPosition } from '../../util/canvas-position.util';
import type { ExperienceStudioCanvasPosition } from '../../util/canvas-position.util';
import template from './sw-experience-studio-canvas.html.twig';
import './sw-experience-studio-canvas.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop' | 'custom';
type ViewportPreset = Exclude<Viewport, 'custom'>;
type ResizeDirection = 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w' | 'nw';
type GestureType = 'move' | 'resize' | 'pan';

type CanvasGesture = {
    type: GestureType;
    direction: ResizeDirection | null;
    pointerId: number;
    startClientX: number;
    startClientY: number;
    startFrameX: number;
    startFrameY: number;
    startFrameWidth: number;
    startFrameHeight: number;
    startPanX: number;
    startPanY: number;
};

const VIEWPORT_WIDTHS: Record<ViewportPreset, number> = {
    mobile: 375,
    'tablet-landscape': 768,
    desktop: 1480,
};

const DEFAULT_FRAME_HEIGHT = 900;
const MIN_FRAME_WIDTH = 320;
const MAX_FRAME_WIDTH = 2400;
const MIN_FRAME_HEIGHT = 280;
const MAX_FRAME_HEIGHT = 1800;
const MIN_CANVAS_ZOOM = 0.1;
const MAX_CANVAS_ZOOM = 1.5;
const POSITION_LIMIT = 5000;
const CANVAS_ZOOM_SNAP_THRESHOLD = 0.01;
const VIEWPORT_TRANSITION_DURATION = 300;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layoutId: {
            type: String,
            required: false,
            default: '',
        },
        layoutName: {
            type: String,
            required: false,
            default: '',
        },
        viewport: {
            type: String as PropType<Viewport>,
            required: false,
            default: 'desktop',
        },
        settingsWidth: {
            type: Number,
            required: false,
            default: 320,
        },
        isResizingSettings: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'viewport-change',
        'settings-resize-start',
        'settings-close',
    ],

    data() {
        return {
            activePanel: 'structure' as 'structure' | null,
            isSettingsOpen: false,
            frameX: 0,
            frameY: 0,
            frameWidth: VIEWPORT_WIDTHS.desktop,
            frameHeight: DEFAULT_FRAME_HEIGHT,
            canvasZoom: 1,
            canvasPanX: 0,
            canvasPanY: 0,
            canvasGesture: null as CanvasGesture | null,
            canvasPointerMoveHandler: null as ((event: PointerEvent) => void) | null,
            canvasPointerEndHandler: null as (() => void) | null,
            canvasPointerTarget: null as HTMLElement | null,
            isViewportTransitioning: false,
            viewportTransitionTimer: null as number | null,
            resizeDirections: [
                'n',
                'ne',
                'e',
                'se',
                's',
                'sw',
                'w',
                'nw',
            ] as ResizeDirection[],
        };
    },

    computed: {
        backgroundStyle(): Record<string, string> {
            const gridSize = 24 * this.canvasZoom;
            const toBackgroundPosition = (offset: number): string =>
                `calc(50% ${offset < 0 ? '-' : '+'} ${Math.abs(offset)}px)`;

            return {
                backgroundSize: `${gridSize}px ${gridSize}px`,
                backgroundPosition: `${toBackgroundPosition(this.canvasPanX)} ${toBackgroundPosition(this.canvasPanY)}`,
            };
        },

        worldStyle(): Record<string, string> {
            return {
                transform: `translate3d(${this.canvasPanX}px, ${this.canvasPanY}px, 0) scale(${this.canvasZoom})`,
            };
        },

        frameStyle(): Record<string, string> {
            return {
                width: `${this.frameWidth}px`,
                height: `${this.frameHeight}px`,
                transform: `translate(-50%, -50%) translate(${this.frameX}px, ${this.frameY}px)`,
            };
        },

        isDraggingCanvas(): boolean {
            return this.canvasGesture !== null;
        },
    },

    watch: {
        layoutId(): void {
            this.restoreCanvasPosition();
            void this.$nextTick(() => this.fitCanvasToFrame());
        },

        viewport(viewport: Viewport): void {
            if (viewport !== 'custom') {
                this.applyViewportPreset(viewport);
            }
        },
    },

    mounted(): void {
        void this.$nextTick(() => {
            this.frameHeight = this.getDefaultFrameHeight();
            this.restoreCanvasPosition();
            this.fitCanvasToFrame();
        });
    },

    beforeUnmount(): void {
        this.stopCanvasGesture();

        if (this.viewportTransitionTimer !== null) {
            window.clearTimeout(this.viewportTransitionTimer);
        }
    },

    methods: {
        getCurrentUserId(): string | null {
            const currentUser = Shopware.Store.get('session').currentUser as { id?: string } | null;

            return currentUser?.id ?? null;
        },

        getWorkspace(): HTMLElement | null {
            return (this.$refs.workspace as HTMLElement | undefined) ?? null;
        },

        getDefaultFrameHeight(): number {
            const availableHeight = this.getWorkspace()?.clientHeight ?? DEFAULT_FRAME_HEIGHT + 96;

            return Math.max(MIN_FRAME_HEIGHT, Math.min(MAX_FRAME_HEIGHT, availableHeight - 96));
        },

        restoreCanvasPosition(): void {
            const position: ExperienceStudioCanvasPosition | null = loadExperienceStudioCanvasPosition(
                this.getCurrentUserId(),
                this.layoutId,
            );

            this.frameX = position?.x ?? 0;
            this.frameY = position?.y ?? 0;
        },

        saveCanvasPosition(): void {
            saveExperienceStudioCanvasPosition(this.getCurrentUserId(), this.layoutId, {
                x: this.frameX,
                y: this.frameY,
            });
        },

        clampPosition(value: number): number {
            return Math.max(-POSITION_LIMIT, Math.min(POSITION_LIMIT, value));
        },

        clamp(value: number, minimum: number, maximum: number): number {
            return Math.max(minimum, Math.min(maximum, value));
        },

        normalizeCanvasZoom(zoom: number): number {
            const clampedZoom = this.clamp(zoom, MIN_CANVAS_ZOOM, MAX_CANVAS_ZOOM);

            if (Math.abs(clampedZoom - 1) <= CANVAS_ZOOM_SNAP_THRESHOLD + Number.EPSILON) {
                return 1;
            }

            return clampedZoom;
        },

        onViewportChange(viewport: ViewportPreset): void {
            this.startViewportTransition();
            this.applyViewportPreset(viewport);
            this.$emit('viewport-change', viewport);
            void this.$nextTick(() => this.fitCanvasToFrame());
        },

        startViewportTransition(): void {
            this.isViewportTransitioning = true;

            if (this.viewportTransitionTimer !== null) {
                window.clearTimeout(this.viewportTransitionTimer);
            }

            this.viewportTransitionTimer = window.setTimeout(() => {
                this.isViewportTransitioning = false;
                this.viewportTransitionTimer = null;
            }, VIEWPORT_TRANSITION_DURATION);
        },

        applyViewportPreset(viewport: ViewportPreset): void {
            this.frameWidth = VIEWPORT_WIDTHS[viewport];
            this.frameHeight = this.getDefaultFrameHeight();
        },

        togglePanel(panel: 'structure' | 'settings'): void {
            if (panel === 'structure') {
                this.activePanel = this.activePanel === 'structure' ? null : 'structure';

                return;
            }

            if (this.isSettingsOpen) {
                this.closeSettingsPanel();

                return;
            }

            this.openSettingsPanel();
        },

        openSettingsPanel(): void {
            this.isSettingsOpen = true;
        },

        closeStructurePanel(): void {
            this.activePanel = null;
        },

        closeSettingsPanel(): void {
            if (!this.isSettingsOpen) {
                return;
            }

            this.isSettingsOpen = false;
            this.$emit('settings-close');
        },

        closePanel(): void {
            this.activePanel = null;
            this.closeSettingsPanel();
        },

        onBackgroundPointerDown(event: PointerEvent): void {
            const background = this.$refs.background as HTMLElement | undefined;

            if (event.button !== 0 || event.target !== background) {
                return;
            }

            this.startCanvasGesture(event, 'pan');
        },

        onCanvasWheel(event: WheelEvent): void {
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

        onFrameMoveStart(event: PointerEvent): void {
            this.startCanvasGesture(event, 'move');
        },

        onFrameResizeStart(event: PointerEvent, direction: ResizeDirection): void {
            if (event.button !== 0) {
                return;
            }

            this.$emit('viewport-change', 'custom');
            this.startCanvasGesture(event, 'resize', direction);
        },

        startCanvasGesture(event: PointerEvent, type: GestureType, direction: ResizeDirection | null = null): void {
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

        onCanvasPointerMove(event: PointerEvent): void {
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

                return;
            }

            this.applyFrameResize(gesture, deltaX, deltaY);
        },

        applyFrameResize(gesture: CanvasGesture, deltaX: number, deltaY: number): void {
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
        },

        stopCanvasGesture(): void {
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
                this.saveCanvasPosition();
            }

            this.canvasPointerMoveHandler = null;
            this.canvasPointerEndHandler = null;
            this.canvasPointerTarget = null;
            this.canvasGesture = null;
        },

        fitCanvasToFrame(): void {
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

        resetCanvasZoom(): void {
            this.canvasZoom = 1;
            this.canvasPanX = -this.frameX;
            this.canvasPanY = -this.frameY;
        },

        changeCanvasZoom(delta: number): void {
            const previousZoom = this.canvasZoom;
            const nextZoom = this.normalizeCanvasZoom(previousZoom + delta);
            const zoomRatio = nextZoom / previousZoom;

            this.canvasZoom = nextZoom;
            this.canvasPanX *= zoomRatio;
            this.canvasPanY *= zoomRatio;
        },

        onFrameMoveKeydown(event: KeyboardEvent): void {
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
            this.saveCanvasPosition();
        },

        onFrameResizeKeydown(event: KeyboardEvent, direction: ResizeDirection): void {
            const step = event.shiftKey ? 50 : 10;
            let deltaX = 0;
            let deltaY = 0;

            if ((direction.includes('e') || direction.includes('w')) && event.key === 'ArrowRight') {
                deltaX = step;
            } else if ((direction.includes('e') || direction.includes('w')) && event.key === 'ArrowLeft') {
                deltaX = -step;
            } else if ((direction.includes('n') || direction.includes('s')) && event.key === 'ArrowDown') {
                deltaY = step;
            } else if ((direction.includes('n') || direction.includes('s')) && event.key === 'ArrowUp') {
                deltaY = -step;
            } else {
                return;
            }

            event.preventDefault();
            this.$emit('viewport-change', 'custom');
            const gesture: CanvasGesture = {
                type: 'resize',
                direction,
                pointerId: -1,
                startClientX: 0,
                startClientY: 0,
                startFrameX: this.frameX,
                startFrameY: this.frameY,
                startFrameWidth: this.frameWidth,
                startFrameHeight: this.frameHeight,
                startPanX: this.canvasPanX,
                startPanY: this.canvasPanY,
            };

            this.applyFrameResize(gesture, deltaX, deltaY);
            this.saveCanvasPosition();
        },

        resizeHandleLabel(direction: ResizeDirection): string {
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
    },
});
