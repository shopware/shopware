import template from './sw-tree-item.html.twig';
import './sw-tree-item.scss';

/**
 * @private
 */
export default {
    name: 'sw-tree-item',
    template,

    props: {
        item: {
            type: Object,
            required: true,
            default: {}
        },

        active: {
            type: Boolean,
            required: false,
            default: false
        },

        draggedItem: {
            type: Object,
            required: false,
            default: null
        },

        parentChecked: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            opened: false,
            selected: false,
            isLeaf: false,
            isLoading: false,
            dragEl: null,
            dragStartX: 0,
            dragStartY: 0,
            mouseStartX: 0,
            mouseStartY: 0,
            rootParent: null,
            checked: false
        };
    },

    computed: {
        isDragging() {
            if (this.draggedItem === null) {
                return false;
            }
            return this.draggedItem.data.id === this.item.data.id;
        },

        styling() {
            return {
                'is--dragging': this.isDragging,
                'is--active': this.active,
                'is--opened': this.opened,
                'is--no-children': this.item.childCount <= 0
            };
        },

        dragConf() {
            return {
                delay: 300,
                validDragCls: null,
                dragGroup: 'sw-tree-item',
                data: this.item,
                onDragStart: this.dragStart,
                onDragEnter: this.onMouseEnter,
                onDrop: this.dragEnd,
                preventEvent: false
            };
        }
    },

    created() {
        if (this.parentChecked) {
            this.checked = true;
        }
    },

    updated() {
        this.updatedComponent();
    },

    watch: {
        parentChecked: {
            handler(value) {
                this.checked = value;
            }
        }
    },

    methods: {
        updatedComponent() {
            if (this.item.children.length > 0) {
                this.isLoading = false;
            }
        },

        openTreeItem(open = !this.opened) {
            if (this.isDragging) {
                return;
            }
            this.opened = open;
        },

        getTreeItemChildren(treeItem) {
            if (this.isDragging || this.isLoading) {
                return;
            }

            if (treeItem.children.length <= 0) {
                this.isLoading = true;

                this.getItems(treeItem.data.id);
            }
        },

        getItems(...args) {
            return this.$parent.getItems(args);
        },

        dragStart(config, element, dragElement) {
            if (this.isDragging || this.isLoading) {
                return;
            }

            this.dragEl = dragElement;
            this.$parent.startDrag(this);
        },

        dragEnd() {
            this.$parent.endDrag();
        },

        onMouseEnter(dragData, dropData) {
            if (!dropData) {
                return;
            }

            this.$parent.moveDrag(dragData, dropData);
        },

        // Bubbles this method to the root tree from any item depth
        startDrag(draggedComponent) {
            return this.$parent.startDrag(draggedComponent);
        },

        // Bubbles this method to the root tree from any item depth
        endDrag() {
            this.$parent.endDrag();
        },

        // Bubbles this method to the root tree from any item depth
        moveDrag(draggedComponent, droppedComponent) {
            return this.$parent.moveDrag(draggedComponent, droppedComponent);
        },

        // Bubbles this method to the root tree from any item depth
        emitCheckedItem(event, item) {
            this.$emit('itemChecked', event, item);
        },

        // Checks the item
        toggleItemCheck(event, item) {
            this.checked = event;
            this.item.checked = event;
            this.$emit('itemChecked', event, item);
        }
    }
};
