/**
 * @private
 * @sw-package discovery
 */
export interface ContentElementNode {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    data_requirements?: unknown;
    slots?: Record<string, ContentElementNode[]>;
    provides_context?: unknown;
    accepts_context?: unknown;
}
