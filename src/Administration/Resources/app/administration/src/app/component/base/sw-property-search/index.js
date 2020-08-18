import template from './sw-property-search.html.twig';
import './sw-property-search.scss';

// @deprecated tag:v6.4.0.0 for StateDeprecated
const { Component, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

Component.register('sw-property-search', {
    template,

    props: {
        collapsible: {
            type: Boolean,
            required: false,
            default: true
        },
        overlay: {
            type: Boolean,
            required: false,
            default: true
        },
        options: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            groups: [],
            groupOptions: [],
            displayTree: false,
            preventSelection: false,
            displaySearch: false,
            currentGroup: null,
            searchTerm: '',
            groupPage: 1,
            optionPage: 1,
            groupTotal: 1,
            optionTotal: 1
        };
    },

    computed: {
        swPropertySearchClasses() {
            return { overlay: this.overlay };
        },

        // @deprecated tag:v6.4.0.0
        groupStore() {
            return StateDeprecated.getStore('property_group');
        },

        // @deprecated tag:v6.4.0.0
        optionStore() {
            return StateDeprecated.getStore('property_group_option');
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            if (this.collapsible) {
                document.addEventListener('click', this.closeOnClickOutside);
                document.addEventListener('keyup', this.closeOnClickOutside);
            } else {
                this.showTree();
            }

            this.$parent.$on('options-load', this.addOptionCount);
        },

        destroyedComponent() {
            if (this.collapsible) {
                document.removeEventListener('click', this.closeOnClickOutside);
                document.removeEventListener('keyup', this.closeOnClickOutside);
            }
        },

        selectGroup(group) {
            this.$refs.groupGrid.selectAll(false);
            this.$refs.groupGrid.selectItem(true, group);

            if (!group) {
                this.groupOptions = [];
                return;
            }

            this.currentGroup = group;
            this.optionPage = 1;
            this.loadOptions();
        },

        onOptionSelect(selection, item, selected) {
            if (this.preventSelection) {
                return;
            }

            this.$emit('option-select', { item, selected });
            this.addOptionCount();
        },

        onGroupPageChange(pagination) {
            this.groupPage = pagination.page;
            this.loadGroups();
        },

        onOptionPageChange(pagination) {
            this.optionPage = pagination.page;
            this.loadOptions();
        },

        onOptionSearchPageChange(pagination) {
            this.optionPage = pagination.page;
            this.showSearch();
        },

        onFocusSearch() {
            if (this.searchTerm.length > 0) {
                this.showSearch();
                return;
            }

            this.showTree();
        },

        onSearchOptions: utils.debounce(function debouncedSearch(input) {
            const validInput = input || '';

            this.optionPage = 1;
            this.searchTerm = validInput.trim();
            this.onFocusSearch();
        }, 400),

        closeOnClickOutside(event) {
            if (event.type === 'keyup' && event.key.toLowerCase() !== 'tab') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-property-search') === null) {
                this.displaySearch = false;
                this.displayTree = false;
            }
        },

        selectOptions(grid) {
            grid.selectAll(false);

            this.preventSelection = true;
            this.options.forEach((option) => {
                grid.selectItem(!option.isDeleted, option);
            });
            this.preventSelection = false;
        },

        showSearch() {
            this.displaySearch = true;
            this.displayTree = false;

            const queries = [];
            const terms = this.searchTerm.split(' ');

            terms.forEach((term) => {
                queries.push({
                    query: {
                        type: 'equals',
                        field: 'property_group_option.name',
                        value: term
                    },
                    score: 5000
                });

                queries.push({
                    query: {
                        type: 'contains',
                        field: 'property_group_option.name',
                        value: term
                    },
                    score: 500
                });

                queries.push({
                    query: {
                        type: 'contains',
                        field: 'property_group_option.group.name',
                        value: this.searchTerm
                    },
                    score: 100
                });
            });

            const params = {
                page: this.optionPage,
                limit: 10,
                queries: queries,
                'total-count-mode': 1,
                associations: {
                    group: {}
                }
            };

            this.optionStore.getList(params).then((response) => {
                this.groupOptions = response.items;
                this.optionTotal = response.total;
                this.selectOptions(this.$refs.optionSearchGrid);
            });
        },

        showTree() {
            this.displaySearch = false;
            this.displayTree = true;
            this.groupPage = 1;
            this.optionPage = 1;
            if (this.collapsible) {
                this.groupOptions = [];
            }
            this.loadGroups();
        },

        loadGroups() {
            const params = {
                page: this.groupPage,
                limit: 10,
                'total-count-mode': 1
            };

            this.groupOptions = [];
            this.groupStore.getList(params).then((response) => {
                this.groups = response.items;
                this.groupTotal = response.total;
                this.addOptionCount();
            });
        },

        loadOptions() {
            const params = {
                page: this.optionPage,
                limit: 10,
                'total-count-mode': 1
            };

            if (this.currentGroup.sortingType === 'position') {
                params.sortBy = 'position';
            } else {
                params.sortBy = 'name';
            }

            this.currentGroup.getAssociation('options').getList(params).then((response) => {
                this.groupOptions = response.items;
                this.optionTotal = response.total;
                this.selectOptions(this.$refs.optionGrid);
            });
        },

        refreshSelection() {
            if (this.displayTree) {
                this.selectOptions(this.$refs.optionGrid);
            } else if (this.displaySearch) {
                this.selectOptions(this.$refs.optionSearchGrid);
            }
        },

        addOptionCount() {
            this.groups.forEach((group) => {
                const optionCount = this.options.filter((option) => {
                    return option.groupId === group.id && !option.isDeleted;
                });

                this.$set(group, 'optionCount', optionCount.length);
            });
        }
    }
});
