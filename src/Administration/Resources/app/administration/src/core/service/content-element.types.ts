/**
 * A context entry an element consumes, keyed in `acceptsContext` by the path it reads.
 *
 * A root-scoped entry carrying a `propertyAlias` is how a data mapping is stored: the value found at
 * the key path replaces the element's authored value for the aliased property at render time.
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
