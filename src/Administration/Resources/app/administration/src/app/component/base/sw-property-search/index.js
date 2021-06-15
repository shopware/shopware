import template from './sw-property-search.html.twig';
import './sw-property-search.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

Component.register('sw-property-search', {
    template,

    inject: ['repositoryFactory'],

    props: {
        collapsible: {
            type: Boolean,
            required: false,
            default: true,
        },
        overlay: {
            type: Boolean,
            required: false,
            default: true,
        },
        options: {
            type: Array,
            required: true,
            default() {
                return [];
            },
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
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
            optionTotal: 1,
        };
    },

    computed: {
        swPropertySearchClasses() {
            return { overlay: this.overlay };
        },

        propertyGroupRepository() {
            return this.repositoryFactory.create('property_group');
        },

        propertyGroupCriteria() {
            const criteria = new Criteria();

            criteria.setPage(this.groupPage);
            criteria.setLimit(10);
            criteria.setTotalCountMode(1);

            return criteria;
        },

        propertyGroupOptionRepository() {
            const entity = this.currentGroup ? this.currentGroup.options.entity : 'property_group_option';
            const source = this.currentGroup ? this.currentGroup.options.source : undefined;

            return this.repositoryFactory.create(entity, source);
        },

        propertyGroupOptionCriteria() {
            const criteria = new Criteria();

            criteria.setPage(this.optionPage);
            criteria.setLimit(10);
            criteria.setTotalCountMode(1);
            criteria.setTerm(this.searchTerm);
            criteria.addAssociation('group');

            return criteria;
        },

        propertyGroupOptionSearchCriteria() {
            const criteria = new Criteria();

            const terms = this.searchTerm.split(' ');
            terms.forEach((term) => {
                criteria.addQuery(Criteria.equals('property_group_option.name', term), 5000);
                criteria.addQuery(Criteria.contains('property_group_option.name', term), 500);
                criteria.addQuery(Criteria.equals('property_group_option.group.name', term), 100);
            });

            criteria.setPage(this.optionPage);
            criteria.setLimit(10);
            criteria.setTotalCountMode(1);
            criteria.addAssociation('group');

            return criteria;
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
            this.currentGroup = null;
            this.displaySearch = true;
            this.displayTree = false;

            this.propertyGroupOptionRepository.search(this.propertyGroupOptionCriteria, Shopware.Context.api)
                .then((groupOptions) => {
                    this.groupOptions = groupOptions;
                    this.optionTotal = groupOptions.total;
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
            this.propertyGroupRepository.search(this.propertyGroupCriteria, Shopware.Context.api).then((groups) => {
                this.groups = groups;
                this.groupTotal = groups.total;
                this.addOptionCount();
            });
        },

        loadOptions() {
            const criteria = new Criteria(1, null);

            criteria.setTotalCountMode(1);
            criteria.addAssociation('group');

            this.propertyGroupOptionRepository.search(criteria, Shopware.Context.api)
                .then((groupOptions) => {
                    this.groupOptions = this.sortOptions(groupOptions);
                    this.optionTotal = groupOptions.total;
                    this.selectOptions(this.$refs.optionGrid);
                });
        },

        sortOptions(options) {
            if (options.length > 0 && options[0].group.sortingType === 'alphanumeric') {
                options.sort((a, b) => (a.translated.name.localeCompare(b.translated.name, undefined, { numeric: true })));
            } else {
                options.sort((a, b) => (a.position - b.position));
            }
            const start = (this.optionPage - 1) * 10;
            const end = start + 10;
            options = options.slice(start, end);
            return options;
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
        },
    },
});
