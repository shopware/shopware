import template from './sw-tree-item.html.twig';
import './sw-tree-item.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-tree-item', {
    template,

    inject: {
        feature: {
            from: 'feature',
            default: null,
        },
        getItems: {
            from: 'getItems',
            default: null,
        },
        treeStartDrag: {
            from: 'startDrag',
            default: null,
        },
        treeEndDrag: {
            from: 'endDrag',
            default: null,
        },
        treeMoveDrag: {
            from: 'moveDrag',
            default: null,
        },
        treeAddSubElement: {
            from: 'addSubElement',
            default: null,
        },
        treeAddElement: {
            from: 'addElement',
            default: null,
        },
        treeDuplicateElement: {
            from: 'duplicateElement',
            default: null,
        },
        treeOnFinishNameingElement: {
            from: 'onFinishNameingElement',
            default: null,
        },
        treeOnDeleteElements: {
            from: 'onDeleteElements',
            default: null,
        },
        treeAbortCreateElement: {
            from: 'abortCreateElement',
            default: null,
        },
    },

    compatConfig: Shopware.compatConfig,

    emits: ['check-item'],

    props: {
        item: {
            type: Object,
            required: true,
            default: () => {
                return {};
            },
        },

        draggedItem: {
            type: Object,
            required: false,
            default: () => {
                return null;
            },
        },

        newElementId: {
            type: String,
            required: false,
            default: () => {
                return null;
            },
        },

        translationContext: {
            type: String,
            default: () => {
                return 'sw-tree';
            },
        },

        onChangeRoute: {
            type: Function,
            default: () => {
                return null;
            },
        },

        disableContextMenu: {
            type: Boolean,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
            },
        },

        contextMenuTooltipText: {
            type: String,
            required: false,
            default: () => {
                return null;
            },
        },

        activeParentIds: {
            type: Array,
            required: false,
            default: () => {
                return null;
            },
        },

        activeItemIds: {
            type: Array,
            required: false,
            default: () => {
                return null;
            },
        },

        sortable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
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
            default: () => {
                return '';
            },
        },

        displayCheckbox: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
        },

        allowNewCategories: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
        },

        allowDeleteCategories: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
        },

        allowCreateWithoutPosition: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
            },
        },

        allowDuplicate: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
            },
        },

        getItemUrl: {
            type: Function,
            required: false,
            default: () => {
                return null;
            },
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
                // eslint-disable-next-line vue/no-mutating-props
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
                // eslint-disable-next-line vue/no-side-effects-in-computed-properties,vue/no-mutating-props
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
                'is--disabled': this.item.disabled,
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
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                let parentNode = this.$parent;

                // eslint-disable-next-line
                while (parentNode.$options._componentTag !== 'sw-tree') {
                    if (parentNode.$parent) {
                        parentNode = parentNode.$parent;
                    }

                    break;
                }

                return parentNode;
            }

            return {
                addSubElement: this.treeAddSubElement,
                addElement: this.treeAddElement,
                duplicateElement: this.treeDuplicateElement,
                onFinishNameingElement: this.treeOnFinishNameingElement,
                onDeleteElements: this.treeOnDeleteElements,
                abortCreateElement: this.treeAbortCreateElement,
            };
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

        contentSlot() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.content;
            }

            return this.$slots.content;
        },

        actionsSlot() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.actions;
            }

            return this.$slots.actions;
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
            deep: true,
        },

        activeItemIds: {
            handler() {
                if (this.activeItemIds) {
                    this.checked = this.activeItemIds.indexOf(this.item.id) >= 0;
                }
            },
            immediate: true,
            deep: true,
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
            if (this.item.children.length > 0 || this.item.childCount <= 0) {
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

            this.updatedComponent();
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

                this.getItems(treeItem.data.id, treeItem.data.schema);
            }
        },

        dragStart(config, element, dragElement) {
            if (this.isDragging || this.isLoading) {
                return;
            }

            this.dragEl = dragElement;

            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.startDrag(this);
            } else {
                this.treeStartDrag(this);
            }
        },

        dragEnd() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.endDrag();
            } else {
                this.treeEndDrag();
            }
        },

        onMouseEnter(dragData, dropData) {
            if (!dropData) {
                return;
            }

            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.moveDrag(dragData, dropData);
            } else {
                this.treeMoveDrag(dragData, dropData);
            }
        },

        startDrag(draggedComponent) {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                return this.$parent.$parent.startDrag(draggedComponent);
            }

            return this.treeStartDrag(draggedComponent);
        },

        endDrag() {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                this.$parent.$parent.endDrag();
            } else {
                this.treeEndDrag();
            }
        },

        moveDrag(draggedComponent, droppedComponent) {
            if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
                return this.$parent.$parent.moveDrag(draggedComponent, droppedComponent);
            }
            return this.treeMoveDrag(draggedComponent, droppedComponent);
        },

        // Bubbles this method to the root tree from any item depth
        emitCheckedItem(item) {
            this.$emit('check-item', item);
        },

        // Checks the item
        toggleItemCheck(event, item) {
            if (this.checkedGhost && !item.checked) {
                this.checked = true;
                // eslint-disable-next-line vue/no-mutating-props
                this.item.checked = true;
            } else {
                this.checked = event;
                // eslint-disable-next-line vue/no-mutating-props
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
            this.$nextTick(() => {
                this.parentScope.onFinishNameingElement(draft, event);
            });
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

        renderContentSlotNode({ item, openTreeItem, getName }) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.content({ item, openTreeItem, getName });
            }

            return this.$slots.content({ item, openTreeItem, getName });
        },

        renderActionsSlotNode({ item, openTreeItem }) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return this.$scopedSlots.actions({ item, openTreeItem });
            }

            return this.$slots.actions({ item, openTreeItem });
        },
    },
});
