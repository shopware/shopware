/**
 * @private
 * @sw-package discovery
 */
export interface ContentElementNode {
    id: string;
    component: string;
    properties?: Record<string, unknown>;
    style?: Record<string, unknown>;
    dataRequirements?: unknown;
    slots?: Record<string, ContentElementNode[]>;
    providesContext?: unknown;
    acceptsContext?: unknown;
    attributedSpecifications?: Record<string, string>;
}
