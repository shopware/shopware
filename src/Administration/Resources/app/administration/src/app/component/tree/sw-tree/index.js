import template from './sw-tree.html.twig';
import './sw-tree.scss';

const { Component } = Shopware;
const { debounce, sort } = Shopware.Utils;

/**
 * @package admin
 *
 * @private
 * @status ready
 * @example-type static
 * @description you need to declare the functions createNewElement, getChildrenFromParent in the parent.
 * @component-example
 * <sw-tree
 *     :searchable="false"
 *     :disableContextMenu="() => { return true; }"
 *     :onChangeRoute="() => { return false; }"
 *     :sortable="true"
 *     initiallyExpandedRoot
 *     :items="[
 *         { id: 1, name: 'Example #1', childCount: 4, parentId: null, afterId: null, isDeleted: false },
 *             { id: 6, name: 'Example #6', childCount: 0, parentId: 1, afterId: null },
 *             { id: 7, name: 'Example #7', childCount: 0, parentId: 1, afterId: 6 },
 *             { id: 8, name: 'Example #8', childCount: 0, parentId: 1, afterId: 7 },
 *             { id: 9, name: 'Example #9', childCount: 0, parentId: 1, afterId: 8 },
 *         { id: 2, name: 'Example #2', childCount: 0, parentId: null, afterId: 1 },
 *         { id: 3, name: 'Example #3', childCount: 0, parentId: null, afterId: 2 },
 *         { id: 4, name: 'Example #4', childCount: 0, parentId: null, afterId: 3 },
 *         { id: 5, name: 'Example #5', childCount: 0, parentId: null, afterId: 4 },
 *     ]">
 *     <template #items="{ treeItems, sortable, draggedItem, disableContextMenu, onChangeRoute }">
 *         <sw-tree-item
 *             v-for="(item, index) in treeItems"
 *             :key="item.id"
 *             :item="item"
 *             :disableContextMenu="disableContextMenu"
 *             :onChangeRoute="onChangeRoute"
 *             :sortable="true">
 *         </sw-tree-item>
 *     </template>
 * </sw-tree>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-tree', {
    template,

    inject: ['feature'],

    compatConfig: Shopware.compatConfig,

    provide() {
        if (this.isCompatEnabled('INSTANCE_CHILDREN')) {
            return {
                getItems: this.getItems,
            };
        }

        return {
            getItems: this.getItems,
            startDrag: this.startDrag,
            endDrag: this.endDrag,
            moveDrag: this.moveDrag,
            addSubElement: this.addSubElement,
            addElement: this.addElement,
            duplicateElement: this.duplicateElement,
            onFinishNameingElement: this.onFinishNameingElement,
            onDeleteElements: this.onDeleteElements,
            abortCreateElement: this.abortCreateElement,
        };
    },

    emits: [
        'checked-elements-count',
        'get-tree-items',
        'search-tree-items',
        'drag-start',
        'drag-end',
        'delete-element',
        'editing-end',
        'batch-delete',
        'save-tree-items',
    ],

    props: {
        items: {
            type: Array,
            required: true,
        },

        rootParentId: {
            type: String,
            required: false,
            default: () => {
                return null;
            },
        },

        parentProperty: {
            type: String,
            required: false,
            default: () => {
                return 'parentId';
            },
        },

        afterIdProperty: {
            type: String,
            required: false,
            default: () => {
                return 'afterId';
            },
        },

        childCountProperty: {
            type: String,
            required: false,
            default: () => {
                return 'childCount';
            },
        },

        searchable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
        },

        activeTreeItemId: {
            type: String,
            required: false,
            default: () => {
                return '';
            },
        },

        routeParamsActiveElementId: {
            type: String,
            required: false,
            default: () => {
                return 'id';
            },
        },

        translationContext: {
            type: String,
            required: false,
            default: () => {
                return 'sw-tree';
            },
        },

        onChangeRoute: {
            type: Function,
            required: false,
            default: () => {
                return null;
            },
        },

        disableContextMenu: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
            },
        },

        bindItemsToFolder: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
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

        checkItemsInitial: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
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

        allowCreateCategories: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return true;
            },
        },

        initiallyExpandedRoot: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: () => {
                return false;
            },
        },

        ariaLabel: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            treeItems: [],
            draggedItem: null,
            currentTreeSearch: null,
            newElementId: null,
            contextItem: null,
            currentEditMode: null,
            addElementPosition: null,
            // eslint-disable-next-line vue/no-reserved-keys
            _eventFromEdit: null,
            createdItem: null,
            checkedElements: {},
            checkedElementsCount: 0,
            showDeleteModal: false,
            toDeleteItem: null,
            checkedElementsChildCount: 0,
        };
    },

    computed: {
        activeElementId() {
            return this.$route.params[this.routeParamsActiveElementId] || null;
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
        },

        selectedItemsPathIds() {
            return Object.keys(this.checkedElements).reduce((acc, itemId) => {
                const item = this.findById(itemId);

                // get each parent id
                const pathIds = item?.data?.path?.split('|').filter((pathId) => pathId.length > 0) ?? '';

                // add parent id to accumulator
                return [
                    ...acc,
                    ...pathIds,
                ];
            }, []);
        },

        checkedItemIds() {
            return Object.keys(this.checkedElements);
        },
    },

    watch: {
        items: {
            immediate: true,
            handler() {
                this.treeItems = this.getTreeItems(this.isSearched ? null : this.rootParentId);
                this._eventFromEdit = null;
            },
            deep: true,
        },

        activeTreeItemId(val) {
            if (val && this.activeElementId) {
                this.openTreeById();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    unmounted() {
        this.$emit('checked-elements-count', 0);
    },

    beforeUnmount() {
        this.beforeUnmountedComponent();
    },

    methods: {
        createdComponent() {
            if (this.activeTreeItemId && this.activeElementId) {
                this.openTreeById();
            }
            this.$emit('checked-elements-count', this.checkedElementsCount);
        },

        mountedComponent() {
            // Focus handling
            this.$el.addEventListener('focusin', this.handleFocusIn);
            this.$el.addEventListener('keydown', this.handleKeyDown);
        },

        beforeUnmountedComponent() {
            this.$el.removeEventListener('focusin', this.handleFocusIn);
            this.$el.removeEventListener('keydown', this.handleKeyDown);
        },

        handleFocusIn(event) {
            // Check if focus in already in the tree on any tree item
            if (event.target.classList.contains('sw-tree-item')) {
                // If focus is already on a tree item, do nothing
                return;
            }

            // Check if target is a input element
            if (event.target.tagName === 'INPUT') {
                // If focus is on an input element, do nothing
                return;
            }

            /* Check recursively if any tree item is active, if yes, focus on it.
             * If no tree item is active, focus on the first tree item
             */
            const activeTreeItem = this.$el.querySelector('.sw-tree-item[aria-current="page"]');

            if (activeTreeItem) {
                activeTreeItem.focus();
            } else {
                const firstTreeItem = this.$el.querySelector('.sw-tree-item');

                if (firstTreeItem) {
                    firstTreeItem.focus();
                }
            }
        },

        handleKeyDown(event) {
            switch (event.key) {
                case 'Tab': {
                    // Tab out of the tree to the next focusable element

                    // Add inert attribute to the tree
                    this.$el.setAttribute('inert', '');

                    // Remove inert attribute from the tree after normal tabbing behavior is done
                    setTimeout(() => {
                        this.$el.removeAttribute('inert');
                    }, 0);

                    break;
                }

                case 'ArrowDown': {
                    const currentFocusedTreeItem = this.$el.querySelector('.sw-tree-item:focus');

                    if (!currentFocusedTreeItem) {
                        break;
                    }

                    // Check if current focused tree is open
                    const isTreeItemOpen = currentFocusedTreeItem.getAttribute('aria-expanded') === 'true';

                    // If tree item is open, focus on the first child tree item
                    if (isTreeItemOpen) {
                        const firstChildTreeItem = currentFocusedTreeItem.querySelector('.sw-tree-item');

                        if (firstChildTreeItem) {
                            firstChildTreeItem.focus();
                            break;
                        }
                    }

                    const nextTreeItem = currentFocusedTreeItem.nextElementSibling;

                    if (nextTreeItem) {
                        nextTreeItem.focus();
                        break;
                    }

                    // If no next tree item is found, look at the parent tree item
                    const parentTreeItem = currentFocusedTreeItem.parentElement.closest('.sw-tree-item');
                    // Get the next sibling of the parent tree item
                    const nextParentTreeItem = parentTreeItem.nextElementSibling;

                    if (nextParentTreeItem) {
                        nextParentTreeItem.focus();
                        break;
                    }

                    break;
                }

                case 'ArrowUp': {
                    const currentFocusedTreeItem = document.activeElement;

                    // Check if current focused tree item is a tree item
                    if (!currentFocusedTreeItem.classList.contains('sw-tree-item')) {
                        break;
                    }

                    // Helper function to find the last visible child in an expanded tree
                    const getLastVisibleChild = (treeItem) => {
                        const isExpanded = treeItem?.getAttribute('aria-expanded') === 'true';
                        if (isExpanded) {
                            const children = treeItem.querySelectorAll('.sw-tree-item');
                            return children[children.length - 1]; // Last child in expanded tree
                        }
                        return null;
                    };

                    // Step 1: Try to focus on the previous sibling
                    let previousTreeItem = currentFocusedTreeItem.previousElementSibling;
                    if (previousTreeItem) {
                        // If previous sibling is expanded, go to its last child
                        const lastChild = getLastVisibleChild(previousTreeItem);
                        if (lastChild) {
                            lastChild.focus();
                        } else {
                            previousTreeItem.focus();
                        }
                        break;
                    }

                    // Step 2: No previous sibling, try to go to the parent
                    let parentTreeItem = currentFocusedTreeItem.parentElement.closest('.sw-tree-item');
                    while (parentTreeItem) {
                        if (parentTreeItem.previousElementSibling) {
                            // Go to the last visible child of the parent's previous sibling
                            previousTreeItem = parentTreeItem.previousElementSibling;
                            const lastChild = getLastVisibleChild(previousTreeItem);
                            if (lastChild) {
                                lastChild.focus();
                            } else {
                                previousTreeItem.focus();
                            }
                            break;
                        }
                        // If no previous sibling, keep traversing up the tree
                        parentTreeItem = parentTreeItem.parentElement.closest('.sw-tree-item');
                    }

                    // If no parent or sibling found, nothing more to focus on
                    break;
                }

                // Space key
                case ' ': {
                    // Toggle the checkbox of the focused tree item
                    const currentFocusedTreeItem = document.activeElement;

                    // Check if active element is a tree item
                    if (!currentFocusedTreeItem.classList.contains('sw-tree-item')) {
                        break;
                    }

                    const itemId = currentFocusedTreeItem.getAttribute('data-item-id');

                    if (!itemId) {
                        break;
                    }

                    // Get tree item from the recursive this.treeItems array
                    const treeItem = this.findById(itemId);

                    if (!treeItem) {
                        break;
                    }

                    // Toggle the tree item
                    treeItem.checked = !treeItem.checked;
                    this.checkItem(treeItem);

                    break;
                }

                // Enter key
                case 'Enter': {
                    // Change route to the focused tree item
                    const currentFocusedTreeItem = document.activeElement;

                    // Check if active element is a tree item
                    if (!currentFocusedTreeItem.classList.contains('sw-tree-item')) {
                        break;
                    }

                    const itemId = currentFocusedTreeItem.getAttribute('data-item-id');

                    if (!itemId) {
                        break;
                    }

                    // Get tree item from the recursive this.treeItems array
                    const treeItem = this.findById(itemId);

                    if (!treeItem) {
                        break;
                    }

                    this.onChangeRoute(treeItem);
                    break;
                }

                case 'ArrowLeft': {
                    /* Closing is handled by the tree item component.
                     * This event just gets triggered when event is not handled by the tree item component.
                     * Then we need to focus the parent tree item.
                     */
                    const currentFocusedTreeItem = document.activeElement;

                    // Check if active element is a tree item
                    if (!currentFocusedTreeItem.classList.contains('sw-tree-item')) {
                        break;
                    }

                    const parentTreeItem = currentFocusedTreeItem.parentElement.closest('.sw-tree-item');

                    if (parentTreeItem) {
                        parentTreeItem.focus();
                    }

                    break;
                }

                case 'ArrowRight': {
                    /* Opening is handled by the tree item component.
                     * This event just gets triggered when event is not handled by the tree item component.
                     * Then we need to focus the first child tree item.
                     */
                    const currentFocusedTreeItem = document.activeElement;

                    // Check if active element is a tree item
                    if (!currentFocusedTreeItem.classList.contains('sw-tree-item')) {
                        break;
                    }

                    // Check if current focused tree is open
                    const isTreeItemOpen = currentFocusedTreeItem.getAttribute('aria-expanded') === 'true';

                    // If tree item is open, focus on the first child tree item
                    if (!isTreeItemOpen) {
                        break;
                    }

                    const firstChildTreeItem = currentFocusedTreeItem.querySelector('.sw-tree-item');

                    if (firstChildTreeItem) {
                        firstChildTreeItem.focus();
                        break;
                    }

                    break;
                }

                default: {
                    break;
                }
            }
        },

        getItems(parentId = this.rootParentId, searchTerm = null) {
            this.$emit('get-tree-items', parentId, searchTerm);
        },

        searchItems: debounce(function debouncedTreeSearch() {
            this.$emit('search-tree-items', this.currentTreeSearch);
        }, 600),

        getTreeItems(parentId) {
            const treeItems = [];
            this.items.forEach((item) => {
                if (item.isDeleted) {
                    return;
                }

                if (parentId === null && typeof this.items.find((i) => i.id === item.parentId) !== 'undefined') {
                    return;
                }

                if (parentId !== null && item[this.parentProperty] !== parentId) {
                    return;
                }

                const hasChildCountProperty = item.hasOwnProperty(this.childCountProperty);
                const childCount = hasChildCountProperty ? item[this.childCountProperty] : 0;

                const alreadyLoadedTreeItem = this.findById(item.id);

                treeItems.push({
                    data: item,
                    id: item.id,
                    schema: item.schema,
                    parentId: parentId,
                    childCount: childCount,
                    children: this.getTreeItems(item.id),
                    initialOpened: this.initiallyExpandedRoot && item.parentId === null,
                    active: false,
                    activeElementId: this.routeParamsActiveElementId,
                    checked: alreadyLoadedTreeItem?.checked ?? !!this.checkItemsInitial,
                    disabled: item.disabled,
                    disabledToolTipText: item.disabledToolTipText,
                    [this.afterIdProperty]: item[this.afterIdProperty],
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
            this.$emit('drag-start');
        },

        endDrag() {
            if (!this.droppedItem) {
                this.draggedItem = null;
                return;
            }

            const oldParentId = this.draggedItem.data.parentId;
            const newParentId = this.droppedItem.data.parentId;

            // item moved into other tree, update count
            if (oldParentId !== newParentId) {
                if (oldParentId !== null) {
                    const draggedParent = this.findById(oldParentId);
                    if (draggedParent) {
                        draggedParent.childCount -= 1;
                        draggedParent.data.childCount -= 1;
                    }
                }

                if (newParentId !== null) {
                    const droppedParent = this.findById(newParentId);
                    droppedParent.childCount += 1;
                    droppedParent.data.childCount += 1;
                }

                this.draggedItem.data.parentId = this.droppedItem.data.parentId;
            }

            const tree = this.findTreeByParentId(oldParentId);
            this.updateSorting(tree);

            if (oldParentId !== this.droppedItem.parentId) {
                const dropTree = this.findTreeByParentId(this.droppedItem.parentId);
                this.updateSorting(dropTree);
            }

            // bundle drag event data for consumer
            const eventData = {
                draggedItem: this.draggedItem,
                droppedItem: this.droppedItem,
                oldParentId,
                newParentId,
            };

            // reset event items
            this.draggedItem = null;
            this.droppedItem = null;

            this.isLoading = true;

            this.$emit('drag-end', eventData);
        },

        moveDrag(draggedComponent, droppedComponent) {
            if (!draggedComponent || !droppedComponent) {
                return;
            }

            if (draggedComponent.id === droppedComponent.id) {
                return;
            }

            const sourceTree = this.findTreeByParentId(draggedComponent.parentId);
            const targetTree = this.findTreeByParentId(droppedComponent.parentId);

            const dragItemIdx = sourceTree.findIndex((i) => i.id === draggedComponent.id);
            const dropItemIdx = targetTree.findIndex((i) => i.id === droppedComponent.id);

            if (dragItemIdx < 0 || dropItemIdx < 0) {
                return;
            }

            droppedComponent = targetTree[dropItemIdx];

            if (!this.bindItemsToFolder || draggedComponent.parentId === droppedComponent.parentId) {
                sourceTree.splice(dragItemIdx, 1);
                targetTree.splice(dropItemIdx, 0, draggedComponent);

                if (draggedComponent.parentId !== droppedComponent.parentId) {
                    draggedComponent.parentId = droppedComponent.parentId;
                }
            }

            this.droppedItem = droppedComponent;
        },

        openTreeById(id = this.activeElementId) {
            const item = this.findById(id);

            if (item === null) {
                return;
            }

            if (this.activeElementId === item.id) {
                item.active = true;
            } else {
                item.initialOpened = true;
            }
            const activeElementParentId = item.parentId;

            if (item.parentId !== null) {
                this.openTreeById(activeElementParentId);
            }
        },

        findTreeByParentId(parentId) {
            const queue = [{ id: null, children: this.treeItems }];

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

        findById(id) {
            const queue = [{ id: null, children: this.treeItems }];

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
        },

        onCreateNewItem(name) {
            if (!name.length || name.length <= 0) {
                return;
            }

            let newElem = null;
            newElem = this.$parent.$parent.createNewElement(null, null, name);

            this.saveItems();

            const item = this.getNewTreeItem(newElem);

            this.addElement(item, 'after');
        },

        addSubElement(contextItem) {
            if (!contextItem || !contextItem.data || !contextItem.data.id) {
                return;
            }

            if (this.contextItem === null) {
                this.contextItem = contextItem;
            }
            this.currentEditMode = this.addSubElement;

            this.$parent.$parent.getChildrenFromParent(contextItem.id).then(() => {
                const parentElement = contextItem;
                const newElem = this.$parent.$parent.createNewElement(contextItem, contextItem.id);
                const newTreeItem = this.getNewTreeItem(newElem);

                parentElement.childCount += 1;
                parentElement.data.childCount += 1;
                this.newElementId = newElem.id;
                this.createdItem = newTreeItem;
            });
        },

        duplicateElement(contextItem) {
            this.$parent.$parent.duplicateElement(contextItem);
        },

        addElement(contextItem, pos) {
            let newElem = null;
            newElem = this.$parent.$parent.createNewElement(contextItem);

            const newTreeItem = this.getNewTreeItem(newElem);

            if (this.contextItem === null) {
                this.contextItem = contextItem;
            }
            if (this.addElementPosition === null) {
                this.addElementPosition = pos;
            }

            if (!contextItem.hasOwnProperty('parentId')) {
                contextItem.parentId = null;
            }

            this.currentEditMode = this.addElement;

            const targetTree = this.findTreeByParentId(contextItem.parentId);

            const newItemIdx = this.treeItems.findIndex((i) => i.id === newTreeItem.id);
            const contextItemIdx = targetTree.findIndex((i) => i.id === contextItem.id);

            if (pos === 'before') {
                targetTree.splice(contextItemIdx, 1, newTreeItem, contextItem);
            } else {
                this.contextItem = newTreeItem;
                targetTree.splice(contextItemIdx, 1, contextItem, newTreeItem);
            }

            this.treeItems.splice(newItemIdx, 1);
            this.updateSorting(targetTree);
            this.newElementId = newElem.id;
            this.createdItem = newTreeItem;
        },

        getNewTreeItem(elem) {
            const hasChildCountProperty = elem.hasOwnProperty(this.childCountProperty);
            const childCount = hasChildCountProperty ? elem[this.childCountProperty] : 0;

            const hasParentProperty = elem.hasOwnProperty('parentId');
            const parentId = hasParentProperty ? elem.parentId : null;

            return {
                data: elem,
                id: elem.id,
                parentId: parentId,
                childCount: childCount,
                children: 0,
                initialOpened: false,
                active: false,
            };
        },

        deleteElement(item) {
            const targetTree = this.findTreeByParentId(item.parentId);
            const deletedItemIdx = targetTree.findIndex((i) => i.id === item.id);
            if (item.children.length > 0) {
                item.children.forEach((child) => {
                    child.data.isDeleted = true;
                });
            }
            targetTree.splice(deletedItemIdx, 1);
            this.updateSorting(targetTree);
            this.$emit('delete-element', item);
            this.saveItems();
        },

        abortCreateElement(item) {
            if (this._eventFromEdit) {
                this._eventFromEdit = null;
                return;
            }

            if (this.currentEditMode !== null) {
                this.deleteElement(item);

                const parent = this.findById(item.parentId);
                if (parent.id === item.parentId && parent.data) {
                    parent.childCount -= 1;
                    parent.data.childCount -= 1;
                }
            }

            this.contextItem = null;
            this.newElementId = null;
            this.currentEditMode = null;
            this.addElementPosition = null;
            this.$emit('editing-end', { parentId: item.parentId });
        },

        onFinishNameingElement(draft, event) {
            if (this.createdItem) {
                this.createdItem.data.save().then(() => {
                    this.createdItem = null;
                    this.saveItems();
                    if (this.currentEditMode !== null && this.contextItem) {
                        this.currentEditMode(this.contextItem, this.addElementPosition);
                    }
                });
            }
            this._eventFromEdit = event;
            this.newElementId = null;
        },

        deleteSelectedElements() {
            if (this.checkedElements.length <= 0) {
                return;
            }

            const batchDeleteIsFunction = this.isCompatEnabled('INSTANCE_LISTENERS')
                ? typeof this.$listeners['batch-delete'] === 'function'
                : typeof this.$attrs.onBatchDelete === 'function';

            if (batchDeleteIsFunction) {
                this.$emit('batch-delete', this.checkedElements);
            } else {
                Object.values(this.checkedElements).forEach((itemId) => {
                    const item = this.findById(itemId);
                    if (item) {
                        this.deleteElement(item);
                    }
                });
            }

            this.checkedElements = {};
            this.checkedElementsCount = 0;
            this.checkedElementsChildCount = 0;
            this.$emit('checked-elements-count', this.checkedElementsCount);
        },

        checkItem(item) {
            if (item.checked) {
                if (item.childCount > 0) {
                    this.checkedElementsChildCount += 1;
                }
                if (this.isCompatEnabled('INSTANCE_SET')) {
                    this.$set(this.checkedElements, item.id, item.id);
                } else {
                    this.checkedElements[item.id] = item.id;
                }
                this.checkedElementsCount += 1;
            } else {
                if (item.childCount > 0) {
                    this.checkedElementsChildCount -= 1;
                }
                if (this.isCompatEnabled('INSTANCE_DELETE')) {
                    this.$delete(this.checkedElements, item.id);
                } else {
                    delete this.checkedElements[item.id];
                }
                this.checkedElementsCount -= 1;
            }

            this.$emit('checked-elements-count', this.checkedElementsCount);
        },

        saveItems() {
            this.$emit('save-tree-items');
        },

        onDeleteElements(item) {
            this.toDeleteItem = item;
            this.showDeleteModal = true;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.toDeleteItem = null;
        },

        onConfirmDelete() {
            if (this.toDeleteItem) {
                this.deleteElement(this.toDeleteItem);
            } else {
                this.deleteSelectedElements();
            }
            this.showDeleteModal = false;
            this.toDeleteItem = null;
        },
    },
});
