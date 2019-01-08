import template from './sw-tree-item.html.twig';
import './sw-tree-item.less';

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

        sortable: {
            type: Boolean,
            required: false,
            default: false
        },

        draggedItem: {
            type: Object,
            required: false,
            default: null
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
            mouseStartY: 0
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
                'is--active': this.active,
                'is--dragging': this.isDragging,
                'is--sortable': this.sortable,
                'is--opened': this.opened,
                'is--no-children': this.item.childCount <= 0
            };
        }
    },

    updated() {
        this.updatedComponent();
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

        onDragStart(event) {
            if (this.isDragging || this.isLoading || !this.sortable
                || event.target.classList.contains('sw-tree-item__label')) {
                return;
            }

            this.mouseStartX = event.pageX;
            this.mouseStartY = event.pageY;

            this.dragEl = this.createDragEl();
            document.body.appendChild(this.dragEl);

            window.addEventListener('pointermove', this.onDragMove);
            window.addEventListener('pointerup', this.onDragEnd);

            this.$parent.startDrag(this);
        },

        onDragMove(event) {
            this.dragEl.style.left = `${(event.pageX - this.mouseStartX) + this.dragStartX}px`;
            this.dragEl.style.top = `${(event.pageY - this.mouseStartY) + this.dragStartY}px`;
        },

        onDragEnd() {
            window.removeEventListener('pointermove', this.onDragMove);
            window.removeEventListener('pointerup', this.onDragEnd);

            this.dragStartX = 0;
            this.dragStartY = 0;
            this.mouseStartX = 0;
            this.mouseStartY = 0;

            this.dragEl.remove();
            this.dragEl = null;

            this.$parent.endDrag();
        },

        onMouseEnter() {
            if (!this.draggedItem || this.draggedItem === null) {
                return;
            }

            this.$parent.moveDrag(this);
        },

        createDragEl() {
            const dragEl = this.$el.cloneNode(true);
            const boundingBox = this.$el.getBoundingClientRect();

            this.dragStartX = boundingBox.left;
            this.dragStartY = boundingBox.top;

            dragEl.classList.add('is--dragging-el');
            dragEl.style.width = `${boundingBox.width}px`;
            dragEl.style.position = 'absolute';
            dragEl.style.left = `${this.dragStartX}px`;
            dragEl.style.top = `${this.dragStartY}px`;
            dragEl.style.zIndex = 99999;

            return dragEl;
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
        moveDrag(droppedComponent) {
            return this.$parent.moveDrag(droppedComponent);
        }
    }
};
