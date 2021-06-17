import template from './sw-tree-item.html.twig';
import './sw-tree-item.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-tree-item', {
    template,

    props: {
        item: {
            type: Object,
            required: true,
            default() {
                return {};
            },
        },

        draggedItem: {
            type: Object,
            required: false,
            default: null,
        },

        newElementId: {
            type: String,
            required: false,
            default: null,
        },

        translationContext: {
            type: String,
            default: 'sw-tree',
        },

        onChangeRoute: {
            type: Function,
            default: null,
        },

        disableContextMenu: {
            type: Boolean,
            default: false,
        },

        contextMenuTooltipText: {
            type: String,
            required: false,
            default: null,
        },

        activeParentIds: {
            type: Array,
            required: false,
            default: null,
        },

        activeItemIds: {
            type: Array,
            required: false,
            default: null,
        },

        sortable: {
            type: Boolean,
            required: false,
            default: true,
        },

        markInactive: {
            type: Boolean,
            required: false,
            default: false,
        },

        shouldFocus: {
            type: Boolean,
            required: false,
            default: false,
        },

        shouldShowActiveState: {
            type: Boolean,
            required: false,
            default: false,
        },

        activeFocusId: {
            type: String,
            required: false,
            default: '',
        },

        displayCheckbox: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowNewCategories: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowDeleteCategories: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowCreateWithoutPosition: {
            type: Boolean,
            default: false,
            required: false,
        },

        allowDuplicate: {
            type: Boolean,
            required: false,
            default: false,
        },

        getItemUrl: {
            type: Function,
            required: false,
            default: null,
        },

        getIsHighlighted: {
            type: Function,
            required: false,
            default: () => {
                return false;
            },
        },
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
            checkedGhost: false,
            currentEditElement: null,
        };
    },

    computed: {
        checked: {
            get() {
                return this.item.checked;
            },
            set(isChecked) {
                this.item.checked = isChecked;
            },
        },

        activeElementId() {
            return this.$route.params[this.item.activeElementId] || null;
        },

        isOpened() {
            if (this.item.initialOpened) {
                this.openTreeItem(true);
                this.getTreeItemChildren(this.item);
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties
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
                'is--no-children': this.item.childCount <= 0,
                'is--marked-inactive': this.markInactive && !this.item.data.active,
                'is--focus': this.shouldFocus && this.activeFocusId === this.item.id,
                'is--no-checkbox': !this.displayCheckbox,
                'is--highlighted': this.isHighlighted,
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
                preventEvent: true,
                disabled: !this.sortable,
            };
        },

        parentScope() {
            let parentNode = this.$parent;

            // eslint-disable-next-line
            while (parentNode.$options._componentTag !== 'sw-tree') {
                if (parentNode.$parent) {
                    parentNode = parentNode.$parent;
                }

                break;
            }

            return parentNode;
        },

        toolTip() {
            if (this.contextMenuTooltipText !== null) {
                return {
                    showDelay: 300,
                    message: this.contextMenuTooltipText,
                    disabled: !this.disableContextMenu,
                };
            }

            return {
                showDelay: 300,
                message: this.$tc(`${this.translationContext}.general.actions.actionsDisabledInLanguage`),
                disabled: !this.disableContextMenu,
            };
        },

        isDisabled() {
            return this.currentEditElement !== null || this.disableContextMenu;
        },

        isHighlighted() {
            return this.getIsHighlighted(this.item);
        },
    },

    watch: {
        activeElementId(newId) {
            this.active = newId === this.item.id;
        },

        newElementId(newId) {
            this.currentEditElement = newId;
        },

        activeParentIds: {
            handler() {
                if (this.activeParentIds) {
                    this.checkedGhost = this.activeParentIds.indexOf(this.item.id) >= 0;
                }
            },
            immediate: true,
        },

        activeItemIds: {
            handler() {
                if (this.activeItemIds) {
                    this.checked = this.activeItemIds.indexOf(this.item.id) >= 0;
                }
            },
            immediate: true,
        },
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
                if (this.$el.querySelector('.sw-tree-item.is--active input')) {
                    this.$el.querySelector('.sw-tree-item.is--active input').focus();
                }
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

        getItems(args) {
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
            this.$emit('check-item', item);
        },

        // Checks the item
        toggleItemCheck(event, item) {
            if (this.checkedGhost && !item.checked) {
                this.checked = true;
                this.item.checked = true;
            } else {
                this.checked = event;
                this.item.checked = event;
            }

            this.$emit('check-item', item);
        },

        addSubElement(item) {
            this.parentScope.addSubElement(item);
        },

        addElement(item, pos) {
            this.parentScope.addElement(item, pos);
        },

        duplicateElement(contextItem) {
            this.parentScope.duplicateElement(contextItem);
        },

        onDuplicate(item) {
            this.duplicateElement(item);
            this.openTreeItem(true);
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
        },

        getName(item) {
            if (item.data.translated) {
                return item.data.name || item.data.translated.name;
            }

            return item.data.name;
        },

        getActiveIconColor(item) {
            if (item.data?.active) {
                return item.data.active === true ? '#37d046' : '#d1d9e0';
            }

            return '#d1d9e0';
        },

        showItemUrl(item) {
            if (this.getItemUrl) {
                return this.getItemUrl(item);
            }

            return false;
        },
    },
});
