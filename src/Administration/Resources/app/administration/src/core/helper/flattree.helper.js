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
    constructor(sorting = () => 0, defaultPosition = 1) {
        this._registeredNodes = new Map();
        this._defaultPosition = defaultPosition;
        this._sorting = sorting;
    }

    /**
     * Converts the flat objects into the data tree hierarchy
     *
     * @returns {Array} registered nodes as a data tree
     */
    convertToTree() {
        return this._tree(this._registeredNodes);
    }

    /**
     * Internal method which will be called recursively to build up the tree
     *
     * @private
     * @param {String|undefined} parent
     * @param {Map|Array} elements
     * @param {Number} [level=1]
     * @returns {Array}
     */
    _tree(elements, level = 1, parent) {
        const children = [];
        elements.forEach((element) => {
            if (element.parent !== parent) {
                return;
            }
            element.level = level;

            const newParent = element.id || element.path;
            element.children = this._tree(elements, level + 1, newParent);
            children.push(element);
        });

        return children.sort(this._sorting);
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
                node,
            );
            return this;
        }

        if (hasOwnProperty(node, 'link') && !hasOwnProperty(node, 'target')) {
            node.target = '_self';
        }

        if (!node.position) {
            node.position = this._defaultPosition;
        }

        if (this._registeredNodes.has(nodeIdentifier)) {
            warn(
                'FlatTree',
                `Tree contains node with unique identifier ${nodeIdentifier} already.`,
                'Please remove it first before adding a new one.',
                this._registeredNodes.get(nodeIdentifier),
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
        this._registeredNodes.delete(nodeIdentifier);
        return this;
    }

    /**
     * Returns the collection of the registered nodes for the data tree
     *
     * @deprecated tag:v6.5.0 will be removed as registered nodes should be private
     *
     * @returns {Map}
     */
    getRegisteredNodes() {
        return this._registeredNodes;
    }

    /**
     * @deprecated tag:v6.5.0 will be removed. treat as private
     */
    get defaultPosition() {
        return this._defaultPosition;
    }

    /**
     * @deprecated tag:v6.5.0 set in constructor. treat as private
     */
    set defaultPosition(value) {
        this._defaultPosition = value;
    }
}

export default FlatTree;
