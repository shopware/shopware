/**
 * @sw-package framework
 */

/**
 * @typedef {object} ParsedAttribute
 * @property {string} name
 * @property {string | true} value
 * @property {boolean} quoted
 * @property {boolean} hasValue
 * @property {number} index
 */

class Attributes {
    /**
     * Keeps parsed attribute records together with lookup helpers used by normalization.
     *
     * @param {ParsedAttribute[]} attributes
     */
    constructor(attributes) {
        this.attributes = attributes;
    }

    /**
     * Finds the original parsed attribute record so diagnostics can point at its offset.
     *
     * @param {string} name
     * @returns {ParsedAttribute | undefined}
     */
    get(name) {
        return this.attributes.find((attribute) => attribute.name === name);
    }

    /**
     * Exposes all raw records for callers that need to inspect every attribute.
     *
     * @returns {ParsedAttribute[]}
     */
    getAll() {
        return this.attributes;
    }

    /**
     * Detects bound mode attributes that Vue's descriptor drops from `attrs`.
     *
     * @returns {boolean}
     */
    hasBoundAttributes() {
        return this.attributes.some(
            (attribute) =>
                Attributes.isBound(attribute.name, 'sw-component') || Attributes.isBound(attribute.name, 'sw-override'),
        );
    }

    /**
     * Checks whether this setup script is a Shopware transform candidate.
     *
     * @returns {boolean}
     */
    hasShopwareSetupModeAttribute() {
        return this.attributes.some((attribute) => attribute.name === 'sw-component' || attribute.name === 'sw-override');
    }

    /**
     * Matches Vue shorthand and long-form binding syntax for a static attribute name.
     *
     * @param {string} attributeName
     * @param {string} staticName
     * @returns {boolean}
     */
    static isBound(attributeName, staticName) {
        return attributeName === `:${staticName}` || attributeName === `v-bind:${staticName}`;
    }
}

module.exports = {
    Attributes,
};
