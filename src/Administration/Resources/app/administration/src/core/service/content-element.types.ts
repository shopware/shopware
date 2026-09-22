/**
 * A context entry an element consumes.
 *
 * A data mapping is keyed in `acceptsContext` by its destination property and carries the catalogued root
 * path in `sourcePath`. Ordinary context consumers continue to read from their map key.
 *
 * @private
 * @sw-package discovery
 */
export interface ContentElementContextConsumer {
    type: 'single' | 'collection';
    required: boolean;
    redistribute?: boolean;
    consumerAlias?: string | null;
    propertyAlias?: string | null;
    scope?: 'parent' | 'root';
    /**
     * A registered transform the server applies to the resolved value before it fills the property, copied
     * verbatim from the catalogue candidate. The server rejects a mapping pairing a path with any projection
     * but the one that candidate declares, so this is never the Administration's to choose.
     */
    projection?: string | null;
    sourcePath?: string | null;
}

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
    acceptsContext?: Record<string, ContentElementContextConsumer>;
    attributedSpecifications?: Record<string, string>;
}
