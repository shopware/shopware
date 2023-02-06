import template from './sw-flow-trigger.html.twig';
import './sw-flow-trigger.scss';

const { Component, State } = Shopware;
const { mapPropertyErrors, mapState, mapGetters } = Component.getComponentHelper();
const utils = Shopware.Utils;
const { camelCase, capitalizeString } = Shopware.Utils.string;
const { isEmpty } = utils.types;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory', 'businessEventService'],

    props: {
        overlay: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
        eventName: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            events: [],
            isExpanded: false,
            isLoading: false,
            searchTerm: '',
            searchResult: [],
            searchResultFocusItem: {},
            selectedTreeItem: {},
            setInputFocusClass: null,
            removeInputFocusClass: null,
            showConfirmModal: false,
            triggerSelect: {},
        };
    },

    computed: {
        swFlowTriggerClasses() {
            return { overlay: this.overlay };
        },

        formatEventName() {
            if (!this.eventName) {
                return this.eventName;
            }

            return this.getEventName(this.eventName);
        },

        showTreeView() {
            return this.eventTree.length >= 0
                && (this.searchTerm.length <= 0 || this.searchTerm === this.formatEventName);
        },

        eventTree() {
            return this.getEventTree(this.events);
        },

        isTemplate() {
            return this.$route.query?.type === 'template';
        },

        ...mapState('swFlowState', ['flow']),
        ...mapGetters('swFlowState', ['isSequenceEmpty']),
        ...mapPropertyErrors('flow', ['eventName']),
    },

    watch: {
        eventName: {
            immediate: true,
            handler(value) {
                if (!value) {
                    return;
                }

                this.$route.params.eventName = value;
                this.searchTerm = this.getEventName(value);
            },
        },

        searchTerm(value) {
            if (!value || value === this.formatEventName) {
                return;
            }

            const keyWords = value.split(/[\W_]+/ig);

            this.searchResult = this.events.filter(event => {
                const eventName = this.getEventName(event.name).toLowerCase();

                return keyWords.every(key => eventName.includes(key.toLowerCase()));
            });

            // set first item as focus
            if (this.searchResult.length > 0) {
                this.searchResultFocusItem = this.searchResult[0];
            }
        },

        selectedTreeItem(newValue) {
            if (newValue?.id) {
                utils.debounce(() => {
                    const newElement = this.findTreeItemVNodeById(newValue.id).$el;

                    let offsetValue = 0;
                    let foundTreeRoot = false;
                    let actualElement = newElement;

                    while (!foundTreeRoot) {
                        if (actualElement.classList.contains('sw-tree__content')) {
                            foundTreeRoot = true;
                        } else {
                            offsetValue += actualElement.offsetTop;
                            actualElement = actualElement.offsetParent;
                        }
                    }

                    actualElement.scrollTo({
                        top: offsetValue - (actualElement.clientHeight / 2) - 50,
                        behavior: 'smooth',
                    });
                }, 50)();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            document.addEventListener('click', this.handleClickEvent);
            document.addEventListener('keydown', this.handleGeneralKeyEvents);

            this.getBusinessEvents();
        },

        beforeDestroyComponent() {
            document.removeEventListener('click', this.handleClickEvent);
            document.removeEventListener('keydown', this.handleGeneralKeyEvents);
        },

        handleClickEvent(event) {
            const target = event.target;

            if (target.closest('.sw-tree-item .is--no-children.is--disabled')) {
                return;
            }

            if (target.closest('.sw-tree-item .is--no-children .sw-tree-item__content')
            || target.closest('.sw-flow-trigger__search-result')) {
                this.closeDropdown();
                return;
            }

            if (target.closest('.sw-flow-trigger') === null) {
                if (target.closest('svg')) {
                    return;
                }

                this.closeDropdown();

                if (this.searchTerm !== this.formatEventName) {
                    this.searchTerm = this.formatEventName;
                }
            }
        },

        handleGeneralKeyEvents(event) {
            if (event.type !== 'keydown' || !this.isExpanded) {
                return;
            }

            const key = event.key.toLowerCase();

            switch (key) {
                case 'tab':
                case 'escape': {
                    this.closeDropdown();
                    break;
                }

                case 'arrowdown':
                case 'arrowleft':
                case 'arrowright':
                case 'arrowup': {
                    this.handleArrowKeyEvents(event);
                    break;
                }

                case 'enter': {
                    // when user is searching
                    if (this.searchTerm.length > 0 && this.searchTerm !== this.formatEventName) {
                        this.onClickSearchItem(this.searchResultFocusItem);
                        this.closeDropdown();
                    } else {
                        if (this.selectedTreeItem?.childCount > 0) {
                            return;
                        }

                        this.changeTrigger(this.selectedTreeItem);
                        this.closeDropdown();
                    }

                    break;
                }

                default: {
                    break;
                }
            }
        },

        handleArrowKeyEvents(event) {
            const key = event.key.toLowerCase();

            // when user is searching
            if (this.searchTerm.length > 0 && this.searchTerm !== this.formatEventName) {
                switch (key) {
                    case 'arrowdown': {
                        event.preventDefault();
                        this.changeSearchSelection('next');
                        break;
                    }

                    case 'arrowup': {
                        event.preventDefault();
                        this.changeSearchSelection('previous');
                        break;
                    }

                    default: {
                        break;
                    }
                }
                return;
            }

            // when user has tree open
            const actualSelection = this.findTreeItemVNodeById();

            switch (key) {
                case 'arrowdown': {
                    // check if actual selection was found
                    if (actualSelection?.item?.id) {
                        // when selection is open
                        if (actualSelection.opened) {
                            // get first item of child
                            const newSelection = this.getFirstChildById(actualSelection.item.id);
                            if (newSelection) {
                                // update the selected item
                                this.selectedTreeItem = newSelection;
                            }
                            break;
                        }
                        // when selection is not open then get the next sibling
                        let newSelection = this.getSibling(true, actualSelection.item);
                        // when next sibling exists
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
                            break;
                        }

                        // Get the closest visible ancestor to actual section's position.
                        newSelection = this.getClosestSiblingAncestor(actualSelection.item.parentId);
                        // when next parent exists
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
                            break;
                        }
                    }
                    break;
                }

                case 'arrowup': {
                    // check if actual selection was found
                    if (actualSelection?.item?.id) {
                        // when selection is first item in folder
                        const parent = this.findTreeItemVNodeById(actualSelection.item.parentId);
                        if (parent?.item?.children[0].id === actualSelection.item.id) {
                            // then get the parent folder
                            const newSelection = parent.item;
                            if (newSelection) {
                                // update the selected item
                                this.selectedTreeItem = newSelection;
                            }
                            break;
                        }

                        // when selection is not first item then get the previous sibling
                        const newSelection = this.getSibling(false, actualSelection.item);
                        if (newSelection) {
                            // Get the closest visible sibling's descendant to actual selection's position
                            this.selectedTreeItem = this.getClosestSiblingDescendant(newSelection);
                        }
                    }
                    break;
                }

                case 'arrowright': {
                    this.toggleSelectedTreeItem(true);
                    break;
                }

                case 'arrowleft': {
                    const isClosed = !this.toggleSelectedTreeItem(false);

                    // when selection is an item or a closed folder
                    if (isClosed) {
                        // change the selection to the parent
                        const parentId = actualSelection.item.parentId;
                        const parent = this.findTreeItemVNodeById(parentId);

                        if (parent) {
                            this.selectedTreeItem = parent.item;
                        }
                    }

                    break;
                }

                default: {
                    break;
                }
            }
        },

        getClosestSiblingAncestor(parentId) {
            // when sibling does not exists, go to next parent sibling
            const parent = this.findTreeItemVNodeById(parentId);
            const nextParent = this.getSibling(true, parent.item);
            if (nextParent) {
                return nextParent;
            }

            if (!parent?.item?.parentId) {
                return null;
            }

            return this.getClosestSiblingAncestor(parent.item.parentId);
        },

        getClosestSiblingDescendant(item) {
            const foundItemNode = this.findTreeItemVNodeById(item.id);

            if (foundItemNode.opened && foundItemNode.item.childCount > 0) {
                const lastChildIndex = foundItemNode.item.children.length - 1;
                const lastChild = foundItemNode.item.children[lastChildIndex];

                if (lastChild.childCount === 0) {
                    return lastChild;
                }

                return this.getClosestSiblingDescendant(lastChild);
            }

            return item;
        },

        getFirstChildById(itemId, children = this.$refs.flowTriggerTree.treeItems) {
            const foundItem = children.find((child) => child.id === itemId);

            if (foundItem) {
                // return first child
                return foundItem.children[0];
            }

            for (let i = 0; i < children.length; i += 1) {
                const foundItemInChild = this.getFirstChildById(itemId, children[i].children);

                if (foundItemInChild) {
                    return foundItemInChild;
                }
            }

            return null;
        },

        getSibling(isNext, item, children = this.$refs.flowTriggerTree.treeItems) {
            // when no item exists
            if (!item) {
                return null;
            }

            let foundItem = null;
            const itemIndex = children.indexOf(item);

            if (itemIndex < 0) {
                foundItem = null;
            } else {
                foundItem = isNext ? children[itemIndex + 1] : children[itemIndex - 1];
            }

            if (foundItem) {
                return foundItem;
            }

            for (let i = 0; i < children.length; i += 1) {
                const foundItemInChild = this.getSibling(isNext, item, children[i].children);

                if (foundItemInChild) {
                    return foundItemInChild;
                }
            }

            return null;
        },

        changeSearchSelection(type = 'next') {
            const typeValue = (type === 'previous') ? -1 : 1;

            const actualIndex = this.searchResult.indexOf(this.searchResultFocusItem);
            const focusItem = this.searchResult[actualIndex + typeValue];

            if (typeof focusItem !== 'undefined') {
                this.searchResultFocusItem = focusItem;
            }
        },

        toggleSelectedTreeItem(shouldOpen) {
            const vnode = this.findTreeItemVNodeById();

            if (vnode?.openTreeItem && vnode.opened !== shouldOpen) {
                vnode.openTreeItem();
                return true;
            }

            return false;
        },

        findTreeItemVNodeById(itemId = this.selectedTreeItem.id, children = this.$refs.flowTriggerTree.$children) {
            let found = false;

            if (Array.isArray(children)) {
                found = children.find((child) => {
                    if (child?.item?.id) {
                        return child.item.id === itemId;
                    }

                    return false;
                });
            } else if (children?.item?.id) {
                found = children.item.id === itemId;
            }

            if (found) {
                return found;
            }

            let foundInChildren = false;

            // recursion to find vnode
            for (let i = 0; i < children.length; i += 1) {
                foundInChildren = this.findTreeItemVNodeById(itemId, children[i].$children);
                // stop when found in children
                if (foundInChildren) {
                    break;
                }
            }

            return foundInChildren;
        },

        openDropdown({ setFocusClass, removeFocusClass }) {
            // make functions available
            this.setInputFocusClass = setFocusClass;
            this.removeInputFocusClass = removeFocusClass;

            this.setInputFocusClass();
            this.isExpanded = true;

            if (this.isLoading) {
                return;
            }

            // set first item or selected event as focus
            this.$nextTick(() => {
                if (this.searchTerm === this.formatEventName) {
                    const currentEvent = this.eventTree.find(event => event.id === this.eventName);
                    this.selectedTreeItem = currentEvent || this.$refs.flowTriggerTree.treeItems[0];
                }
            });
        },

        closeDropdown() {
            if (this.removeInputFocusClass) {
                this.removeInputFocusClass();
            }

            this.isExpanded = false;
        },

        changeTrigger(item) {
            if (item?.disabled || item?.childCount > 0) {
                return;
            }

            if (this.isSequenceEmpty) {
                const { id } = item.data;

                State.commit('swFlowState/setTriggerEvent', this.getDataByEvent(id));
                State.dispatch('swFlowState/setRestrictedRules', id);
                this.$emit('option-select', id);
            } else {
                this.showConfirmModal = this.flow.eventName !== item.id;
                this.triggerSelect = this.getDataByEvent(item.id);
            }
        },

        onConfirm() {
            State.commit('swFlowState/setTriggerEvent', this.triggerSelect);
            State.dispatch('swFlowState/setRestrictedRules', this.triggerSelect.name);
            this.$emit('option-select', this.triggerSelect.name);
        },

        onCloseConfirm() {
            this.showConfirmModal = false;
            this.triggerSelect = {};
        },


        getBusinessEvents() {
            this.isLoading = true;

            return this.businessEventService.getBusinessEvents()
                .then(events => {
                    this.events = events;
                    State.commit('swFlowState/setTriggerEvent', this.getDataByEvent(this.eventName));
                    State.dispatch('swFlowState/setRestrictedRules', this.eventName);
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        getLastEventName({ parentId = null, id }) {
            const [eventName] = parentId ? id.split('.').reverse() : [id];

            return this.getEventNameTranslated(eventName);
        },

        getDataByEvent(event) {
            return this.events.find(item => item.name === event);
        },

        hasOnlyStopFlow(event) {
            const eventAware = this.events.find(item => item.name === event).aware || [];
            return eventAware.length === 0;
        },

        // Generate tree data which is compatible with sw-tree from business events
        getEventTree(events) {
            const mappedObj = {};

            events.forEach(event => {
                // Split event name by '.'
                const eventNameKeys = event.name.split('.');
                if (eventNameKeys.length === 0) {
                    return;
                }

                /*
                 Group children to parent based on event names.
                 For instance, if event name is 'checkout.customer.deleted',
                 it's considered that customer is checkout's child and deleted is customer's child.
                */
                const generateTreeData = (currentIndex, keyWords, result) => {
                    const currentKey = keyWords[currentIndex];

                    // next key is child of current key
                    const nextKey = keyWords[currentIndex + 1];

                    result[currentKey] = result[currentKey] || {
                        id: currentKey,
                        parentId: null,
                        children: {},
                    };

                    if (!nextKey) {
                        return;
                    }

                    // Put next key into children of current key
                    result[currentKey].children[nextKey] = result[currentKey].children[nextKey] || {
                        id: `${result[currentKey].id}.${nextKey}`,
                        parentId: result[currentKey].id,
                        children: {},
                    };

                    generateTreeData(currentIndex + 1, keyWords, result[currentKey].children);
                };

                generateTreeData(0, eventNameKeys, mappedObj);
            });

            // Convert tree object to array to work with sw-tree
            const convertTreeToArray = (nodes, output = []) => {
                nodes.forEach(node => {
                    const children = node.children ? Object.values(node.children) : [];
                    output.push({
                        id: node.id,
                        name: this.getLastEventName(node),
                        childCount: children.length,
                        parentId: node.parentId,
                        disabled: isEmpty(node.children) && this.hasOnlyStopFlow(node.id),
                        disabledToolTipText: (isEmpty(node.children) && this.hasOnlyStopFlow(node.id))
                            ? this.$tc('sw-flow.detail.trigger.textHint') : null,
                    });

                    if (children.length > 0) {
                        output = convertTreeToArray(children, output);
                    }
                });
                return output;
            };

            return convertTreeToArray(Object.values(mappedObj));
        },

        getBreadcrumb(eventName) {
            if (!eventName) {
                return '';
            }

            const keyWords = eventName.split('.');

            return keyWords.map(key => {
                return capitalizeString(key);
            }).join(' / ').replace(/_|-/g, ' ');
        },

        onClickSearchItem(item) {
            this.searchTerm = this.formatEventName;
            this.searchResult = [];

            if (this.isSequenceEmpty) {
                this.$emit('option-select', item.name);
                State.commit('swFlowState/setTriggerEvent', item);
                State.dispatch('swFlowState/setRestrictedRules', item.name);
            } else {
                this.showConfirmModal = true;
                this.triggerSelect = item;
            }
        },

        getEventName(eventName) {
            if (!eventName) {
                return eventName;
            }

            const keyWords = eventName.split('.');

            return keyWords.map(key => {
                return this.getEventNameTranslated(key);
            }).join(' / ');
        },

        isSearchResultInFocus(item) {
            return item.name === this.searchResultFocusItem.name;
        },

        getEventNameTranslated(eventName) {
            return this.$te(`sw-flow.triggers.${camelCase(eventName)}`)
                ? this.$tc(`sw-flow.triggers.${camelCase(eventName)}`)
                : eventName.replace(/_|-/g, ' ');
        },
    },
};
