/**
 * @private
 * @sw-package discovery
 */
export interface ContentElementNode {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    slots?: Record<string, ContentElementNode[]>;
}
