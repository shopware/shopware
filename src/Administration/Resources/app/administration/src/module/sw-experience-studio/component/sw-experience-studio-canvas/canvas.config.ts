/** @private @sw-package discovery */
export type Viewport = 'mobile' | 'tablet-landscape' | 'desktop' | 'custom';
/** @private @sw-package discovery */
export type ViewportPreset = Exclude<Viewport, 'custom'>;
/** @private @sw-package discovery */
export type ResizeDirection = 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w' | 'nw';
/** @private @sw-package discovery */
export type GestureType = 'move' | 'resize' | 'pan';

/** @private @sw-package discovery */
export type CanvasGesture = {
    type: GestureType;
    visibleFrameIds: string[];
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

/** @private @sw-package discovery */
export type CanvasFrame = {
    id: string;
    layout: Entity<'content_layout'>;
    selectedElementId: string | null;
    viewport: Viewport;
    previewEntityId: string | null;
    isActive: boolean;
};

/** @private @sw-package discovery */
export const VIEWPORT_WIDTHS: Record<ViewportPreset, number> = {
    mobile: 375,
    'tablet-landscape': 768,
    desktop: 1480,
};
/** @private @sw-package discovery */
export const FRAME_GAP = 60;
/** @private @sw-package discovery */
export const FRAME_FOCUS_INSET = 20;

/** @private @sw-package discovery */
export const DEFAULT_FRAME_HEIGHT = 900;
/** @private @sw-package discovery */
export const MIN_FRAME_WIDTH = 320;
/** @private @sw-package discovery */
export const MAX_FRAME_WIDTH = 2400;
/** @private @sw-package discovery */
export const MIN_FRAME_HEIGHT = 280;
/** @private @sw-package discovery */
export const MAX_FRAME_HEIGHT = 1800;
/** @private @sw-package discovery */
export const MIN_CANVAS_ZOOM = Number.MIN_VALUE;
/** @private @sw-package discovery */
export const MAX_CANVAS_ZOOM = 1.5;
/** @private @sw-package discovery */
export const POSITION_LIMIT = 5000;
/** @private @sw-package discovery */
export const CANVAS_ZOOM_SNAP_THRESHOLD = 0.01;
/** @private @sw-package discovery */
export const VIEWPORT_TRANSITION_DURATION = 300;
