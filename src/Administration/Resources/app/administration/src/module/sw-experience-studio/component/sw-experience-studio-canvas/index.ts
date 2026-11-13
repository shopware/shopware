import type { Viewport, ViewportPreset, ResizeDirection, CanvasGesture, CanvasFrame } from './canvas.config';
import {
    VIEWPORT_WIDTHS,
    FRAME_GAP,
    DEFAULT_FRAME_HEIGHT,
    MIN_FRAME_HEIGHT,
    MAX_FRAME_HEIGHT,
    MIN_CANVAS_ZOOM,
    MAX_CANVAS_ZOOM,
    POSITION_LIMIT,
    CANVAS_ZOOM_SNAP_THRESHOLD,
    VIEWPORT_TRANSITION_DURATION,
} from './canvas.config';
import { canvasInteractionHandlers, isCanvasFrameVisible } from './canvas-interaction-handlers';
import type { WorkspaceConfiguration } from '../../types/workspace.types';
import {
    hasExperienceStudioCanvasPositionBeenManuallyPlaced,
    loadExperienceStudioCanvasPosition,
    saveExperienceStudioCanvasPosition,
} from '../../util/canvas-position.util';
import type { ExperienceStudioCanvasPosition } from '../../util/canvas-position.util';
import template from './sw-experience-studio-canvas.html.twig';
import './sw-experience-studio-canvas.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        workspaceConfiguration: {
            type: Object as PropType<WorkspaceConfiguration | null>,
            default: null,
        },
        frames: {
            type: Array as PropType<CanvasFrame[]>,
            required: false,
            default: () => [],
        },
        allowCreate: {
            type: Boolean,
            default: false,
        },
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
        settingsMaxWidth: {
            type: Number,
            required: false,
            default: 800,
        },
        isResizingSettings: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'configuration-change',
        'viewport-change',
        'frame-activate',
        'frame-close',
        'frame-viewport-change',
        'settings-resize-start',
        'settings-resize-keydown',
        'settings-close',
        'open-layout',
        'create-layout',
    ],

    data() {
        return {
            contextMenuPosition: null as { x: number; y: number } | null,
            contextMenuBlurHandler: null as (() => void) | null,
            isHydratingWorkspace: true,
            activePanel: 'layouts' as 'structure' | 'layouts',
            isSettingsOpen: false,
            frameX: 0,
            frameY: 0,
            frameWidth: VIEWPORT_WIDTHS.desktop,
            frameHeight: DEFAULT_FRAME_HEIGHT,
            canvasZoom: 1,
            canvasPanX: 0,
            canvasPanY: 0,
            activeFrameId: '',
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
            frameStates: {} as Record<string, { x: number; y: number; width: number; height: number }>,
        };
    },

    computed: {
        canZoomOut(): boolean {
            return this.canvasZoom > MIN_CANVAS_ZOOM;
        },

        contextMenuReference(): { getBoundingClientRect: () => DOMRect } | undefined {
            const position = this.contextMenuPosition;

            return position ? { getBoundingClientRect: () => new DOMRect(position.x, position.y, 0, 0) } : undefined;
        },

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

        visibleFrames(): CanvasFrame[] {
            if (this.isHydratingWorkspace) {
                return [];
            }

            if (this.canvasGesture) {
                const visibleFrameIds = new Set(this.canvasGesture.visibleFrameIds);

                return this.frames.filter((frame) => visibleFrameIds.has(frame.id));
            }

            const workspace = this.$refs.workspace as HTMLElement | undefined;
            const bounds = workspace?.getBoundingClientRect();

            return this.frames.filter((frame) =>
                isCanvasFrameVisible(this.frameStates[frame.id], bounds, {
                    panX: this.canvasPanX,
                    panY: this.canvasPanY,
                    zoom: this.canvasZoom,
                }),
            );
        },

        frameIds(): string {
            return this.frames.map((frame) => frame.id).join('|');
        },

        isDraggingCanvas(): boolean {
            return this.canvasGesture !== null;
        },
    },

    watch: {
        canvasPanX(): void {
            this.emitConfiguration();
        },
        canvasPanY(): void {
            this.emitConfiguration();
        },
        canvasZoom(): void {
            this.emitConfiguration();
        },
        frameIds(): void {
            this.initializeFrameStates();
            if (this.frames.length === 0) {
                this.closeStructurePanel();
            }
            this.emitConfiguration();
        },
        layoutId(): void {
            if (this.frameStates[this.layoutId]) {
                this.activateFrame(this.layoutId, false);
            } else {
                this.restoreCanvasPosition();
            }
        },

        viewport(viewport: Viewport): void {
            if (viewport !== 'custom' && !this.workspaceConfiguration) {
                this.applyViewportPreset(viewport);
            }
        },
    },

    mounted(): void {
        this.contextMenuBlurHandler = () => this.closeContextMenu();
        window.addEventListener('blur', this.contextMenuBlurHandler);

        void this.$nextTick(() => {
            this.frameHeight = this.getDefaultFrameHeight();
            if (this.workspaceConfiguration) {
                this.frameStates = Object.fromEntries(
                    this.workspaceConfiguration.frames.map((frame) => [
                        frame.layoutId,
                        {
                            x: frame.x,
                            y: frame.y,
                            width: frame.width,
                            height: frame.height,
                        },
                    ]),
                );
                this.canvasPanX = this.workspaceConfiguration.canvas.panX;
                this.canvasPanY = this.workspaceConfiguration.canvas.panY;
                this.canvasZoom = this.workspaceConfiguration.canvas.zoom;
            } else {
                this.restoreCanvasPosition();
            }
            this.initializeFrameStates();
            if (!this.workspaceConfiguration) {
                this.fitCanvasToFrame();
            }
            void this.$nextTick(() => {
                this.isHydratingWorkspace = false;
            });
        });
    },

    beforeUnmount(): void {
        if (this.contextMenuBlurHandler) {
            window.removeEventListener('blur', this.contextMenuBlurHandler);
            this.contextMenuBlurHandler = null;
        }

        this.stopCanvasGesture();

        if (this.viewportTransitionTimer !== null) {
            window.clearTimeout(this.viewportTransitionTimer);
        }
    },

    methods: {
        ...canvasInteractionHandlers,

        onCanvasContextMenu(event: MouseEvent): void {
            if (event.target !== this.$refs.background) {
                return;
            }

            event.preventDefault();
            this.contextMenuPosition = { x: event.clientX, y: event.clientY };
        },

        closeContextMenu(): void {
            this.contextMenuPosition = null;
        },

        onContextMenuOpenChange(open: boolean): void {
            if (!open) {
                this.closeContextMenu();
            }
        },

        getWorkspaceConfiguration(): WorkspaceConfiguration {
            return {
                frames: this.frames
                    .filter((frame) => this.frameStates[frame.id])
                    .map((frame) => ({
                        layoutId: frame.id,
                        ...this.frameStates[frame.id],
                        viewport: frame.viewport,
                        previewEntityId: frame.previewEntityId,
                    })),
                previewSalesChannelId: null,
                canvas: { panX: this.canvasPanX, panY: this.canvasPanY, zoom: this.canvasZoom },
            };
        },
        emitConfiguration(): void {
            if (this.isHydratingWorkspace) {
                return;
            }
            this.$emit('configuration-change', this.getWorkspaceConfiguration());
        },
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
            if (this.workspaceConfiguration) {
                return;
            }
            const position: ExperienceStudioCanvasPosition | null = loadExperienceStudioCanvasPosition(
                this.getCurrentUserId(),
                this.layoutId,
            );

            this.frameX = position?.x ?? 0;
            this.frameY = position?.y ?? 0;
        },

        initializeFrameStates(): void {
            if (this.frames.length === 0) {
                this.activeFrameId = '';

                return;
            }

            this.frames.forEach((frame) => {
                this.frameStates[frame.id] ??= this.createFrameState(frame);
            });

            const activeFrame = this.frames.find((frame) => frame.isActive) ?? this.frames[0];

            if (activeFrame && this.activeFrameId !== activeFrame.id) {
                this.activateFrame(activeFrame.id, false);
            }
        },

        createFrameState(frame: CanvasFrame): { x: number; y: number; width: number; height: number } {
            const savedPosition = this.workspaceConfiguration
                ? null
                : loadExperienceStudioCanvasPosition(this.getCurrentUserId(), frame.id);
            const position = savedPosition ?? { x: this.frameX, y: this.frameY };
            const width = VIEWPORT_WIDTHS[frame.viewport === 'custom' ? 'desktop' : frame.viewport];
            const state = { ...position, width, height: this.getDefaultFrameHeight() };
            const isManuallyPlaced =
                !this.workspaceConfiguration &&
                hasExperienceStudioCanvasPositionBeenManuallyPlaced(this.getCurrentUserId(), frame.id);
            if (isManuallyPlaced) {
                return { ...state, x: savedPosition?.x ?? 0, y: savedPosition?.y ?? 0 };
            }
            const anchorFrameId = this.activeFrameId || this.frames.find((candidate) => candidate.isActive)?.id;
            const anchorState = (anchorFrameId && this.frameStates[anchorFrameId]) || Object.values(this.frameStates)[0];
            if (anchorState) {
                state.x = anchorState.x + (anchorState.width + width) / 2 + FRAME_GAP;
                state.y = anchorState.y;
            }
            state.x = this.getAvailableFrameX(state);
            return state;
        },

        getAvailableFrameX(state: { x: number; y: number; width: number; height: number }): number {
            let x = state.x;
            let overlappingFrame: typeof state | undefined;
            do {
                overlappingFrame = Object.values(this.frameStates).find(
                    (other) =>
                        Math.abs(x - other.x) < (state.width + other.width) / 2 + FRAME_GAP &&
                        Math.abs(state.y - other.y) < (state.height + other.height) / 2 + FRAME_GAP,
                );
                if (overlappingFrame) {
                    x = overlappingFrame.x + (overlappingFrame.width + state.width) / 2 + FRAME_GAP;
                }
            } while (overlappingFrame);
            return x;
        },

        frameStyleFor(frame: CanvasFrame): Record<string, string> {
            const state = this.frameStates[frame.id] ?? {
                x: 0,
                y: 0,
                width: VIEWPORT_WIDTHS[frame.viewport === 'custom' ? 'desktop' : frame.viewport],
                height: this.getDefaultFrameHeight(),
            };

            return {
                width: `${state.width}px`,
                height: `${state.height}px`,
                transform: `translate(-50%, -50%) translate(${state.x}px, ${state.y}px)`,
            };
        },

        activateFrame(layoutId: string, emit = true): void {
            if (this.activeFrameId === layoutId) {
                if (emit) {
                    this.$emit('frame-activate', layoutId);
                }

                return;
            }

            if (this.activeFrameId) {
                this.frameStates[this.activeFrameId] = {
                    x: this.frameX,
                    y: this.frameY,
                    width: this.frameWidth,
                    height: this.frameHeight,
                };
            }

            const frame = this.frames.find((item) => item.id === layoutId);

            if (!frame) {
                return;
            }

            this.activeFrameId = layoutId;
            const state = this.frameStates[layoutId] ?? {
                x: 0,
                y: 0,
                width: VIEWPORT_WIDTHS[frame.viewport === 'custom' ? 'desktop' : frame.viewport],
                height: this.getDefaultFrameHeight(),
            };
            this.frameX = state.x;
            this.frameY = state.y;
            this.frameWidth = state.width;
            this.frameHeight = state.height;

            if (emit) {
                this.$emit('frame-activate', layoutId);
            }
        },

        saveCanvasPosition(isManuallyPlaced = false): void {
            const layoutId = this.activeFrameId || this.layoutId;
            this.frameStates[layoutId] = {
                x: this.frameX,
                y: this.frameY,
                width: this.frameWidth,
                height: this.frameHeight,
            };
            this.emitConfiguration();
            if (this.workspaceConfiguration) {
                return;
            }
            saveExperienceStudioCanvasPosition(
                this.getCurrentUserId(),
                layoutId,
                {
                    x: this.frameX,
                    y: this.frameY,
                },
                isManuallyPlaced,
            );
        },

        syncActiveFrameState(): void {
            if (!this.activeFrameId || !this.frameStates) {
                return;
            }

            const currentState = this.frameStates[this.activeFrameId] ?? {
                x: this.frameX,
                y: this.frameY,
                width: this.frameWidth,
                height: this.frameHeight,
            };

            this.frameStates[this.activeFrameId] = {
                ...currentState,
                x: this.frameX,
                y: this.frameY,
                width: this.frameWidth,
                height: this.frameHeight,
            };
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

        onViewportChange(viewport: ViewportPreset, layoutId?: string): void {
            const targetLayoutId = layoutId ?? this.activeFrameId;

            if (targetLayoutId) {
                this.activateFrame(targetLayoutId, false);
            }

            this.startViewportTransition();
            this.applyViewportPreset(viewport);
            this.$emit('frame-viewport-change', targetLayoutId, viewport);
            if (!this.frames?.length) {
                this.$emit('viewport-change', viewport);
            }
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
            this.saveCanvasPosition?.();
        },

        openStructure(layoutId: string): void {
            this.focusFrame(layoutId);
            this.activePanel = 'structure';
        },

        openSettingsPanel(): void {
            this.activePanel = 'structure';
            this.isSettingsOpen = true;
        },

        closeStructurePanel(): void {
            this.activePanel = 'layouts';
        },

        closeSettingsPanel(): void {
            if (!this.isSettingsOpen) {
                return;
            }

            this.isSettingsOpen = false;
            this.$emit('settings-close');
        },

        closePanel(): void {
            this.activePanel = 'layouts';
            this.closeSettingsPanel();
        },
    },
});
