import template from './sw-category-tree-field.html.twig';
import './sw-category-tree-field.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;
const { Criteria } = Shopware.Data;

Component.register('sw-category-tree-field', {
    template,

    inject: ['repositoryFactory'],

    props: {
        categoriesCollection: {
            type: Array,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        placeholder: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isFetching: false,
            isComponentReady: false,
            tagLimit: true,
            categories: [],
            selectedCategories: [],
            isExpanded: false,
            term: '',
            searchResult: [],
            searchResultFocusItem: {},
            setInputFocusClass: null,
            removeInputFocusClass: null,
            selectedTreeItem: '',
        };
    },

    computed: {
        globalCategoryRepository() {
            return this.repositoryFactory.create('category');
        },

        categoryRepository() {
            return this.repositoryFactory.create(this.categoriesCollection.entity, this.categoriesCollection.source);
        },

        visibleTags() {
            return this.tagLimit ? this.categoriesCollection.slice(0, 5) : this.categoriesCollection;
        },

        numberOfHiddenTags() {
            const hiddenTagsLength = this.categoriesCollection.length - this.visibleTags.length;

            return hiddenTagsLength > 0 ? hiddenTagsLength : 0;
        },

        selectedCategoriesItemsIds() {
            return this.categoriesCollection.getIds();
        },

        selectedCategoriesPathIds() {
            return this.categoriesCollection.reduce((acc, item) => {
                // get each parent id
                const pathIds = item.path ? item.path.split('|').filter((pathId) => pathId.length > 0) : '';

                // add parent id to accumulator
                return [...acc, ...pathIds];
            }, []);
        },
    },

    watch: {
        categoriesCollection: {
            handler() {
                // check if categoriesCollection is loaded
                if (this.categoriesCollection.entity && !this.isComponentReady && !this.isFetching) {
                    Promise.all([
                        this.getTreeItems(),
                    ]).then(() => {
                        this.isComponentReady = true;
                    });
                }
            },
            immediate: true,
        },

        term: {
            handler(newTerm) {
                // when user is searching
                if (newTerm.length > 0) {
                    this.searchCategories(newTerm).then((response) => {
                        this.searchResult = response;

                        // set first item as focus
                        if (this.searchResult.total > 0) {
                            this.searchResultFocusItem = this.searchResult.first();
                        }
                    });
                } else {
                    this.$nextTick(() => {
                        if (this.$refs.swTree) {
                            // set first item as focus
                            this.selectedTreeItem = this.$refs.swTree.treeItems[0];
                        }
                    });
                }
            },
            immediate: true,
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

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            document.addEventListener('click', this.closeDropdownOnClickOutside);
            document.addEventListener('keydown', this.handleGeneralKeyEvents);
        },

        destroyedComponent() {
            document.removeEventListener('click', this.closeDropdownOnClickOutside);
            document.removeEventListener('keydown', this.handleGeneralKeyEvents);
        },

        getTreeItems(parentId = null) {
            this.isFetching = true;

            // create criteria
            const categoryCriteria = new Criteria(1, 500);
            categoryCriteria.addFilter(Criteria.equals('parentId', parentId));

            // search for categories
            return this.globalCategoryRepository.search(categoryCriteria, Shopware.Context.api).then((searchResult) => {
                // when requesting root categories, replace the data
                if (parentId === null) {
                    this.categories = searchResult;
                    this.isFetching = false;
                    return Promise.resolve();
                }

                // add new categories
                searchResult.forEach((category) => {
                    this.categories.add(category);
                });

                return Promise.resolve();
            });
        },

        onCheckSearchItem(item) {
            const shouldBeChecked = !this.isSearchItemChecked(item.id);

            this.onCheckItem({
                checked: shouldBeChecked,
                id: item.id,
                data: item,
            });
        },

        onCheckItem(item) {
            const itemIsInCategories = this.categoriesCollection.has(item.id);

            if (item.checked && !itemIsInCategories) {
                if (item.data) {
                    this.categoriesCollection.add(item.data);
                    this.$emit('selection-add', item.data);
                } else {
                    this.categoriesCollection.add(item);
                    this.$emit('selection-add', item);
                }

                return true;
            }

            this.removeItem(item);
            return false;
        },

        removeItem(item) {
            this.categoriesCollection.remove(item.id);

            if (item.data) {
                this.$emit('selection-remove', item.data);
            } else {
                this.$emit('selection-remove', item);
            }
        },

        searchCategories(term) {
            // create criteria
            const categorySearchCriteria = new Criteria(1, 500);
            categorySearchCriteria.addFilter(Criteria.equals('type', 'page'));
            categorySearchCriteria.setTerm(term);

            // search for categories
            return this.globalCategoryRepository.search(categorySearchCriteria, Shopware.Context.api);
        },

        isSearchItemChecked(itemId) {
            if (this.selectedCategoriesItemsIds.length > 0) {
                return this.selectedCategoriesItemsIds.indexOf(itemId) >= 0;
            }
            return false;
        },

        isSearchResultInFocus(item) {
            return item.id === this.searchResultFocusItem.id;
        },

        getBreadcrumb(item) {
            if (item.breadcrumb) {
                return item.breadcrumb.join(' / ');
            }
            return item.translated.name || item.name;
        },

        getLabelName(item) {
            if (item.breadcrumb && item.breadcrumb.length > 1) {
                return `.. / ${item.translated.name || item.name} `;
            }

            return item.translated.name || item.name;
        },

        onDeleteKeyup() {
            if (this.term.length <= 0 && this.categoriesCollection) {
                const lastItem = this.categoriesCollection.last();

                this.removeItem(lastItem);
            }
        },

        removeTagLimit() {
            this.tagLimit = false;
        },

        openDropdown({ setFocusClass, removeFocusClass }) {
            this.isExpanded = true;

            // make functions available
            this.setInputFocusClass = setFocusClass;
            this.removeInputFocusClass = removeFocusClass;

            // set first item as focus
            this.$nextTick(() => {
                if (this.term.length <= 0) {
                    this.selectedTreeItem = this.$refs.swTree.treeItems[0];
                }
            });

            this.setInputFocusClass();
        },

        closeDropdown() {
            this.isExpanded = false;
        },

        closeDropdownOnClickOutside(event) {
            // when user uses tab key
            if (event.type === 'keydown' && this.removeInputFocusClass) {
                this.removeInputFocusClass();
                this.closeDropdown();
                return;
            }

            const target = event.target;
            let clickedOutside = true;

            // check if the user clicked inside the dropdown
            if (target.closest('.sw-category-tree-field') === this.$refs.swCategoryTreeField) {
                clickedOutside = false;
            } else if (target instanceof SVGElement || target.parentNode instanceof SVGElement) {
                // check for clicking on svg arrows
                clickedOutside = false;
            }

            if (clickedOutside) {
                if (this.removeInputFocusClass) {
                    this.removeInputFocusClass();
                    this.closeDropdown();
                }
            }
        },

        handleGeneralKeyEvents(event) {
            if (event.type !== 'keydown' || !this.isExpanded) {
                return;
            }

            const key = event.key.toLowerCase();

            switch (key) {
                case 'tab': {
                    this.closeDropdownOnClickOutside(event);
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
                    let newItem = null;

                    // when user is searching
                    if (this.term.length > 0) {
                        newItem = this.searchResultFocusItem;
                    } else {
                        newItem = this.selectedTreeItem;
                    }

                    newItem.checked = !newItem.checked;
                    this.onCheckItem(newItem);

                    // reset search term
                    this.term = '';

                    break;
                }

                case 'escape': {
                    this.closeDropdownOnClickOutside(event);
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
            if (this.term.length > 0) {
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
                        const newSelection = this.getSibling(true, actualSelection.item);
                        // when next sibling exists
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
                        } else {
                            // when sibling does not exists, go to next parent sibling
                            const parent = this.findTreeItemVNodeById(actualSelection.item.parentId);
                            const nextParent = this.getSibling(true, parent.item);
                            if (nextParent) {
                                // update the selected item
                                this.selectedTreeItem = nextParent;
                            }
                        }
                    }
                    break;
                }

                case 'arrowup': {
                    // check if actual selection was found
                    if (actualSelection?.item?.id) {
                        // when selection is first item in folder
                        if (actualSelection.item.data.afterCategoryId === null && actualSelection.item.parentId) {
                            // then get the parent folder
                            const newSelection = this.findTreeItemVNodeById(actualSelection.item.parentId).item;
                            if (newSelection) {
                                // update the selected item
                                this.selectedTreeItem = newSelection;
                            }
                            break;
                        }

                        // when selection is not first item then get the previous sibling
                        const newSelection = this.getSibling(false, actualSelection.item);
                        if (newSelection) {
                            // update the selected item
                            this.selectedTreeItem = newSelection;
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

        changeSearchSelection(type = 'next') {
            const typeValue = (type === 'previous') ? -1 : 1;

            const actualIndex = this.searchResult.indexOf(this.searchResultFocusItem);
            const focusItem = this.searchResult[actualIndex + typeValue];

            if (typeof focusItem !== 'undefined') {
                this.searchResultFocusItem = focusItem;
            }
        },

        getFirstChildById(itemId, children = this.$refs.swTree.treeItems) {
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

        getSibling(isNext, item, children = this.$refs.swTree.treeItems) {
            // when no item exists
            if (!item) {
                return null;
            }

            let foundItem = null;

            if (isNext) {
                foundItem = children.find((child) => child.data.afterCategoryId === item.id);
            } else {
                foundItem = children.find((child) => child.id === item.data.afterCategoryId);

                if (foundItem) {
                    const foundItemNode = this.findTreeItemVNodeById(foundItem.id);

                    if (foundItemNode.opened && foundItemNode.item.children[0]) {
                        const lastChildIndex = foundItemNode.item.children.length - 1;
                        return foundItemNode.item.children[lastChildIndex];
                    }
                }
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

        toggleSelectedTreeItem(shouldOpen) {
            const vnode = this.findTreeItemVNodeById();

            if (vnode?.openTreeItem && vnode.opened !== shouldOpen) {
                vnode.openTreeItem();
                vnode.getTreeItemChildren(vnode.item);
                return true;
            }

            return false;
        },

        findTreeItemVNodeById(itemId = this.selectedTreeItem.id, children = this.$refs.swTree.$children) {
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
    },
});
