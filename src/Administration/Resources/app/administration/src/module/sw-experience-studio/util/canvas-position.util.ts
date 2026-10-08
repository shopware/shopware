/**
 * @private
 * @sw-package discovery
 */
export type ExperienceStudioCanvasPosition = {
    x: number;
    y: number;
};

const STORAGE_KEY_PREFIX = 'sw-experience-studio-canvas-position';

function getStorageKey(userId: string, layoutId: string): string {
    return `${STORAGE_KEY_PREFIX}.${userId}.${layoutId}`;
}

/**
 * @private
 * @sw-package discovery
 */
export function loadExperienceStudioCanvasPosition(
    userId: string | null,
    layoutId: string,
): ExperienceStudioCanvasPosition | null {
    if (!userId || !layoutId || typeof localStorage === 'undefined') {
        return null;
    }

    try {
        const storedPosition = localStorage.getItem(getStorageKey(userId, layoutId));

        if (!storedPosition) {
            return null;
        }

        const position = JSON.parse(storedPosition) as Partial<ExperienceStudioCanvasPosition>;

        if (typeof position.x !== 'number' || !Number.isFinite(position.x)) {
            return null;
        }

        if (typeof position.y !== 'number' || !Number.isFinite(position.y)) {
            return null;
        }

        return {
            x: position.x,
            y: position.y,
        };
    } catch {
        return null;
    }
}

/**
 * @private
 * @sw-package discovery
 */
export function saveExperienceStudioCanvasPosition(
    userId: string | null,
    layoutId: string,
    position: ExperienceStudioCanvasPosition,
): void {
    if (!userId || !layoutId || typeof localStorage === 'undefined') {
        return;
    }

    try {
        localStorage.setItem(getStorageKey(userId, layoutId), JSON.stringify(position));
    } catch {
        // Ignore unavailable or full browser storage. The canvas remains usable for this session.
    }
}
