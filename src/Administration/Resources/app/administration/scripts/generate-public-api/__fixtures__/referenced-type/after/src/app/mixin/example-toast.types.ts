/**
 * @sw-package framework
 */

/**
 * @public
 */
export type ExampleToastVariant = 'info' | 'critical' | 'positive';

/**
 * @public
 */
export interface ExampleToast {
    variant?: ExampleToastVariant;
    title?: string;
    message: string;
    duration?: number;
}

/**
 * @public
 */
export const EXAMPLE_TOAST_DEFAULT_DURATION = 5000;
