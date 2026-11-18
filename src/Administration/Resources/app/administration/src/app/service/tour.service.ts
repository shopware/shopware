/**
 * @sw-package framework
 */

import type { RouteLocationRaw } from 'vue-router';
import type { PopperPlacement, StepOptions } from 'shepherd.js';

/**
 * @private
 */
export interface TourStepDefinition extends Omit<StepOptions, 'attachTo'> {
    selector?: string;
    waitFor?: string;
    route?: RouteLocationRaw;
    position?: PopperPlacement;
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
