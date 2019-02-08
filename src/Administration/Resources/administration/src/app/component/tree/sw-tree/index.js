import utils from 'src/core/service/util.service';
import template from './sw-tree.html.twig';
import './sw-tree.scss';

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-tree>
 * </sw-tree>
 */
export default {
    name: 'sw-tree',
    template,

    props: {
        items: {
            type: Array,
            required: true
        },

        rootParentId: {
            type: String,
            required: false,
            default: null
        },

        parentProperty: {
            type: String,
            required: false,
            default: 'parentId'
        },

        positionProperty: {
            type: String,
            required: false,
            default: 'position'
        },

        childCountProperty: {
            type: String,
            required: false,
            default: 'childCount'
        },

        searchable: {
            type: Boolean,
            required: false,
            default: true
        },

        createFirstItem: {
            type: Function,
            required: true
        }
    },

    data() {
        return {
            draggedItem: null,
            currentTreeSearch: null,
            isLoading: false
        };
    },

    computed: {
        treeItems() {
            return this.getTreeItems(this.isSearched ? false : this.rootParentId);
        },

        isSortable() {
            if (this.currentTreeSearch !== null) {
                return false;
            }

            return this.sortable;
        },

        isSearched() {
            return this.currentTreeSearch !== null && this.currentTreeSearch.length > 0;
        },

        hasActionSlot() {
            return this.$slots && this.$slots.actions;
        },

        hasNoItems() {
            if (this.items.length === 1 && this.items[0] && this.items[0].isDeleted) {
                return true;
            }

            return this.items.length < 1;
        }
    },

    methods: {
        getItems(parentId = this.rootParentId, searchTerm = null) {
            this.$emit('getTreeItems', parentId, searchTerm);
        },

        searchItems: utils.debounce(function debouncedTreeSearch() {
            this.$emit('searchTreeItems', this.currentTreeSearch);
        }, 600),

        getTreeItems(parentId) {
            const treeItems = [];

            this.items.forEach((item) => {
                if (item.isDeleted) {
                    return;
                }

                if (parentId === false && typeof this.items.find(i => i.id === item.parentId) !== 'undefined') {
                    return;
                }

                if (parentId !== false && item[this.parentProperty] !== parentId) {
                    return;
                }
                treeItems.push({
                    data: item,
                    id: item.id,
                    parentId: parentId,
                    position: item[this.positionProperty],
                    childCount: item[this.childCountProperty],
                    children: this.getTreeItems(item.id),
                    checked: false
                });
            });
            treeItems.sort((a, b) => {
                if (a[this.positionProperty] < b[this.positionProperty]) return -1;
                if (a[this.positionProperty] > b[this.positionProperty]) return 1;
                return 0;
            });

            return treeItems;
        },

        startDrag(draggedComponent) {
            draggedComponent.opened = false;
            this.draggedItem = draggedComponent.item;
            this.$emit('sw-tree-on-drag-start');
        },

        endDrag() {
            if (this.draggedItem.parentId !== this.droppedItem.parentId) {
                if (this.draggedItem.parentId !== null) {
                    const draggedParent = this.findById(this.treeItems, this.draggedItem.parentId);
                    draggedParent.data.childCount -= 1;
                }
                if (this.droppedItem.parentId !== null) {
                    const droppedParent = this.findById(this.treeItems, this.droppedItem.parentId);
                    droppedParent.data.childCount += 1;
                }
            }

            this.draggedItem = null;
            this.droppedItem = null;
            this.$emit('sw-tree-on-drag-end');
        },

        moveDrag(draggedComponent, droppedComponent) {
            if (!draggedComponent ||
                !droppedComponent ||
                draggedComponent.id === droppedComponent.id) {
                return;
            }
            const dragItem = draggedComponent.data;
            const dropItem = droppedComponent.data;

            if (dragItem[this.parentProperty] !== dropItem[this.parentProperty]) {
                const leftParentId = dragItem[this.parentProperty];

                dragItem[this.parentProperty] = dropItem[this.parentProperty];

                this.updateChildPositions(leftParentId, dragItem[this.positionProperty]);
                dragItem[this.positionProperty] = this.items.length;
            }

            if (dragItem[this.positionProperty] < dropItem[this.positionProperty]) {
                if (!droppedComponent.opened) {
                    this.moveItemsUp(dragItem, dropItem);
                }
            } else if (dragItem[this.positionProperty] >= dropItem[this.positionProperty]) {
                this.moveItemsDown(dragItem, dropItem);
            }
            this.droppedItem = droppedComponent;
        },

        updateChildPositions(parentId, startPosition) {
            this.items.filter(item => item[this.parentProperty] === parentId).forEach((item) => {
                if (item[this.positionProperty] > startPosition) {
                    item[this.positionProperty] -= 1;
                }
            });
        },

        moveItemsUp(dragItem, dropItem) {
            const dragStartPosition = dragItem[this.positionProperty];
            dragItem[this.positionProperty] = dropItem[this.positionProperty];

            this.items.forEach((item) => {
                if (item.id === dragItem.id ||
                    item[this.positionProperty] > dragItem[this.positionProperty] ||
                    item[this.positionProperty] < dragStartPosition ||
                    item[this.parentProperty] !== dropItem[this.parentProperty]) {
                    return;
                }

                item[this.positionProperty] -= 1;
            });
        },

        moveItemsDown(dragItem, dropItem) {
            const dragStartPosition = dragItem[this.positionProperty];
            dragItem[this.positionProperty] = dropItem[this.positionProperty];

            this.items.forEach((item) => {
                if (item.id === dragItem.id ||
                    item[this.positionProperty] < dragItem[this.positionProperty] ||
                    item[this.positionProperty] > dragStartPosition ||
                    item[this.parentProperty] !== dropItem[this.parentProperty]) {
                    return;
                }

                item[this.positionProperty] += 1;
            });
        },

        findById(object, id) {
            if (object.id === id) {
                return object;
            }
            let result;
            Object.keys(object).filter((key) => {
                return key === 'children' || !Number.isNaN(Number.parseInt(key, 0));
            }).some((key) => {
                if (object.hasOwnProperty(key) && typeof object[key] === 'object') {
                    result = this.findById(object[key], id);
                    if (result) {
                        return result;
                    }
                }
                return null;
            });
            return result;
        }
    }
};
