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
            default() {
                return {};
            }
        },

        draggedItem: {
            type: Object,
            required: false,
            default: null
        },

        newElementId: {
            type: String,
            required: false,
            default: null
        },

        translationContext: {
            type: String,
            default: 'sw-tree'
        },

        onChangeRoute: {
            type: Function,
            default: null
        },

        disableContextMenu: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            opened: this.item.initialOpened,
            active: this.item.active,
            selected: false,
            isLeaf: false,
            isLoading: false,
            dragEl: null,
            dragStartX: 0,
            dragStartY: 0,
            mouseStartX: 0,
            mouseStartY: 0,
            rootParent: null,
            checked: false,
            currentEditElement: null
        };
    },

    watch: {
        activeElementId(newId) {
            this.active = newId === this.item.id;
        },
        newElementId(newId) {
            this.currentEditElement = newId;
        }
    },

    computed: {
        activeElementId() {
            return this.$route.params.id || null;
        },

        isOpened() {
            if (this.item.initialOpened) {
                this.openTreeItem(true);
                this.getTreeItemChildren(this.item);
                this.item.initialOpened = false;
            }
            return this.opened;
        },

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
                'is--opened': this.isOpened,
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
                preventEvent: true
            };
        },

        parentScope() {
            let parentNode = this.$parent;
            // eslint-disable-next-line
            while (parentNode.$options._componentTag !== 'sw-tree') {
                parentNode = parentNode.$parent;
            }
            return parentNode;
        },

        toolTip() {
            return {
                showDelay: 300,
                message: this.$tc(`${this.translationContext}.general.actions.actionsDisabledInLanguage`),
                disabled: !this.disableContextMenu
            };
        },

        isDisabled() {
            return this.currentEditElement !== null || this.disableContextMenu;
        }
    },

    updated() {
        this.updatedComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        updatedComponent() {
            if (this.item.children.length > 0) {
                this.isLoading = false;
            }
        },

        mountedComponent() {
            if (this.item.active) {
                this.$el.querySelector('.sw-tree-item.is--active input').focus();
            }
            if (this.newElementId) {
                this.currentEditElement = this.newElementId;
                this.editElementName();
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
        emitCheckedItem(item) {
            this.$emit('itemChecked', item);
        },

        // Checks the item
        toggleItemCheck(event, item) {
            this.checked = event;
            this.item.checked = event;
            this.$emit('itemChecked', item);
        },

        addSubElement(item) {
            this.parentScope.addSubElement(item);
        },

        addElement(item, pos) {
            this.parentScope.addElement(item, pos);
        },

        editElementName() {
            this.$nextTick(() => {
                const elementNameField = this.$el.querySelector('.sw-tree-detail__edit-tree-item input');
                if (elementNameField) {
                    elementNameField.focus();
                }
            });
        },

        onFinishNameingElement(draft, event) {
            this.parentScope.onFinishNameingElement(draft, event);
        },

        onBlurTreeItemInput(item) {
            this.abortCreateElement(item);
        },

        onCancelSubmit(item) {
            this.abortCreateElement(item);
        },

        abortCreateElement(item) {
            this.parentScope.abortCreateElement(item);
        },

        deleteElement(item) {
            this.parentScope.onDeleteElements(item);
        }
    }
};
