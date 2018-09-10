import { warn } from 'src/core/service/utils/debug.utils';
import { hasOwnProperty } from 'src/core/service/utils/object.utils';

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
        this._defaultPosition = 1000;
    }

    /**
     * Converts the flat objects into the data tree hierarchy
     *
     * @returns {Array} registered nodes as a data tree
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

            const newParent = element.id || element.path;
            element.children = this._tree(newParent, elements);
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
        const nodeIdentifier = node.id || node.path;

        if (!nodeIdentifier) {
            warn(
                'FlatTree',
                'The node needs an "id" or "path" property. Abort registration.',
                node
            );
            return this;
        }

        if (hasOwnProperty(node, 'link') && !hasOwnProperty(node, 'target')) {
            node.target = '_self';
        }

        if (!node.position) {
            node.position = this.defaultPosition;
        }

        if (this._registeredNodes.has(nodeIdentifier)) {
            warn(
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

    get defaultPosition() {
        return this._defaultPosition;
    }

    set defaultPosition(value) {
        this._defaultPosition = value;
    }
}

export default FlatTree;
