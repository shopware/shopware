import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-tree.html.twig';
import './sw-tree.less';

Component.register('sw-tree', {
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

        sortable: {
            type: Boolean,
            required: false,
            default: false
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
                    children: this.getTreeItems(item.id)
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
        },

        endDrag() {
            this.draggedItem = null;
        },

        moveDrag(droppedComponent) {
            if (!this.draggedItem ||
                this.draggedItem === null ||
                this.draggedItem.data.id === droppedComponent.item.data.id) {
                return;
            }

            const dragItem = this.draggedItem.data;
            const dropItem = droppedComponent.item.data;

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
        }
    }
});
