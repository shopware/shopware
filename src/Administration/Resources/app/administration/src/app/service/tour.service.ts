/**
 * @sw-package framework
 */

/**
 * @private
 */
export interface TourStepDefinition {
    selector?: string | Element;
    title?: string;
    text?: string;
    waitFor?: string;
    route?: string | Record<string, unknown>;
    position?: string;
    attachTo?: { element?: Element | string; on?: string };
    beforeShowPromise?: () => Promise<void>;
    when?: Record<string, unknown>;
    buttons?: unknown[];
    [key: string]: unknown;
}

/**
 * @private
 */
export type TourStepsByKey = Record<string, TourStepDefinition[]>;

const tourRegistry = new Map<string, TourStepsByKey>();

/**
 * @private
 */
export function registerTour(moduleId: string, steps: TourStepsByKey): void {
    tourRegistry.set(moduleId, steps);
}

/**
 * @private
 */
export function getTour(moduleId: string): TourStepsByKey | undefined {
    return tourRegistry.get(moduleId);
}
