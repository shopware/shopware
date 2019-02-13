import utils from 'src/core/service/util.service';
import sort from 'src/core/service/utils/sort.utils';
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

        afterIdProperty: {
            type: String,
            required: false,
            default: 'afterId'
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
        },

        openedTreeById: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            treeItems: [],
            draggedItem: null,
            currentTreeSearch: null,
            isLoading: false,
            activeElementId: this.$route.params.id || null
        };
    },

    watch: {
        items() {
            this.treeItems = this.getTreeItems(this.isSearched ? null : this.rootParentId);
        },
        openedTreeById(val) {
            if (val && this.activeElementId) {
                this.openTreeById();
            }
        }
    },

    computed: {
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

                if (parentId === null && typeof this.items.find(i => i.id === item.parentId) !== 'undefined') {
                    return;
                }

                if (parentId !== null && item[this.parentProperty] !== parentId) {
                    return;
                }

                treeItems.push({
                    data: item,
                    id: item.id,
                    parentId: parentId,
                    childCount: item[this.childCountProperty],
                    children: this.getTreeItems(item.id),
                    initialOpened: false,
                    active: false
                });
            });

            return sort.afterSort(treeItems, this.afterIdProperty);
        },

        updateSorting(items) {
            let lastId = null;

            items.forEach((item) => {
                item.data[this.afterIdProperty] = lastId;
                lastId = item.id;
            });

            return items;
        },

        startDrag(draggedComponent) {
            draggedComponent.opened = false;
            this.draggedItem = draggedComponent.item;
            this.$emit('sw-tree-on-drag-start');
        },

        endDrag() {
            if (!this.droppedItem) {
                return;
            }

            // item moved into other tree, update count
            if (this.draggedItem.data.parentId !== this.droppedItem.data.parentId) {
                if (this.draggedItem.parentId !== null) {
                    const draggedParent = this.findById(this.treeItems, this.draggedItem.parentId);
                    draggedParent.childCount -= 1;
                    draggedParent.data.childCount -= 1;
                }

                if (this.droppedItem.parentId !== null) {
                    const droppedParent = this.findById(this.treeItems, this.droppedItem.parentId);
                    droppedParent.childCount += 1;
                    droppedParent.data.childCount += 1;
                }

                this.draggedItem.data.parentId = this.droppedItem.data.parentId;
            }

            const tree = this.findTreeByParentId(this.treeItems, this.draggedItem.parentId);
            this.updateSorting(tree);

            // reset event items
            this.draggedItem = null;
            this.droppedItem = null;

            this.$emit('sw-tree-on-drag-end');
        },

        moveDrag(draggedComponent, droppedComponent) {
            if (!draggedComponent || !droppedComponent) {
                return;
            }

            if (draggedComponent.id === droppedComponent.id) {
                return;
            }

            const sourceTree = this.findTreeByParentId(this.treeItems, draggedComponent.parentId);
            const targetTree = this.findTreeByParentId(this.treeItems, droppedComponent.parentId);

            const dragItemIdx = sourceTree.findIndex(i => i.id === draggedComponent.id);
            const dropItemIdx = targetTree.findIndex(i => i.id === droppedComponent.id);

            if (dragItemIdx < 0 || dropItemIdx < 0) {
                return;
            }

            if (draggedComponent.parentId !== droppedComponent.parentId) {
                sourceTree.splice(dragItemIdx, 1);
                targetTree.splice(dropItemIdx, 1, draggedComponent);
                targetTree.splice(dropItemIdx + 1, 0, droppedComponent);
                draggedComponent.parentId = droppedComponent.parentId;
            } else {
                targetTree.splice(dropItemIdx, 1, draggedComponent);
                sourceTree.splice(dragItemIdx, 1, droppedComponent);
            }

            this.droppedItem = droppedComponent;
        },

        openTreeById(id = this.activeElementId) {
            const item = this.findById(this.treeItems, id);
            if (this.activeElementId === item.id) {
                item.active = true;
            } else {
                item.initialOpened = true;
            }
            const activeElementParentId = item.parentId;

            if (item.parentId !== null) {
                this.openTreeById(activeElementParentId);
            }
            if (this.$parent.$refs[`treeItem.${id}`].length > 0) {
                this.$parent.$refs[`treeItem.${id}`][0].openTreeItem(true);
                this.$parent.$refs[`treeItem.${id}`][0].getTreeItemChildren(item);
            }
        },

        findTreeByParentId(tree, parentId) {
            const queue = [{ id: null, children: tree }];

            while (queue.length > 0) {
                const next = queue.shift();

                if (next.id === parentId) {
                    return next.children;
                }

                if (next.children.length) {
                    queue.push(...next.children);
                }
            }

            return null;
        },

        findById(tree, id) {
            const queue = [{ id: null, children: tree }];

            while (queue.length > 0) {
                const next = queue.shift();

                if (next.id === id) {
                    return next;
                }

                if (next.children.length) {
                    queue.push(...next.children);
                }
            }

            return null;
        }
    }
};
