import utils from 'src/core/service/util.service';

/**
 * The flat tree converts a collection of flat objects into a data tree hierarchy.
 * @class
 */
class FlatTree {
    /**
     * Creates a new instance of registered nodes.
     *
     * @memberOf FlatTree
     * @constructor
     */
    constructor() {
        this._registeredNodes = new Map();
    }

    /**
     * Converts the flat objects into the data tree hierarchy
     *
     * @returns {Object} registered nodes as a data tree
     */
    convertToTree() {
        return this._tree(undefined, this._registeredNodes);
    }

    /**
     * Internal method which will be called recursively to build up the tree
     *
     * @private
     * @param {String|undefined} parent
     * @param {Map|Array} elements
     * @returns {Array}
     */
    _tree(parent, elements) {
        const children = [];
        elements.forEach((element) => {
            if (element.parent !== parent) {
                return;
            }
            element.children = this._tree(element.path, elements);
            children.push(element);
        });

        return children;
    }

    /**
     * Adds a new flat node to the {@link #_registeredNodes} collection map.
     *
     * @chainable
     * @param {Object} node
     * @returns {FlatTree}
     */
    add(node) {
        const nodeIdentifier = node.path;

        if (this._registeredNodes.has(nodeIdentifier)) {
            utils.warn(
                'FlatTree',
                `Tree contains node with unique identifier ${nodeIdentifier} already.`,
                'Please remove it first before adding a new one.',
                this._registeredNodes.get(nodeIdentifier)
            );
            return this;
        }

        this._registeredNodes.set(nodeIdentifier, node);
        return this;
    }

    /**
     * Removes a node using the provided nodeIdentifier, if it is registered.
     *
     * @chainable
     * @param {String} nodeIdentifier - router path of the node
     * @returns {FlatTree}
     */
    remove(nodeIdentifier) {
        if (!this._registeredNodes.has(nodeIdentifier)) {
            return this;
        }

        this._registeredNodes.delete(nodeIdentifier);
        return this;
    }

    /**
     * Returns the collection of the registered nodes for the data tree
     * @returns {Map}
     */
    getRegisteredNodes() {
        return this._registeredNodes;
    }
}

export default FlatTree;
