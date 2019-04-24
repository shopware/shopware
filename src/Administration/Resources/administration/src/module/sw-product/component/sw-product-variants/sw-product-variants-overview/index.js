import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-variants-overview.html.twig';
import './sw-products-variants-overview.scss';

Component.register('sw-product-variants-overview', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing')
    ],

    data() {
        return {
            variantList: [],
            isLoading: false,
            showDeleteModal: false,
            modalLoading: false,
            priceEdit: false,
            filterOptions: [],
            activeFilter: [],
            includeOptions: [],
            filterWindowOpen: false
        };
    },

    props: {
        product: {
            type: Object,
            required: true
        },

        selectedGroups: {
            type: Array,
            required: true
        }
    },

    watch: {
        'selectedGroups'() {
            this.getFilterOptions();
        }
    },

    computed: {
        variantColumns() {
            return this.getVariantColumns();
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        }
    },

    methods: {
        getVariantColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: this.$tc('sw-product.variations.generatedListColumnVariation'),
                    allowResize: true
                },
                {
                    property: 'price',
                    dataIndex: 'price.gross',
                    label: this.$tc('sw-product.variations.generatedListColumnPrice'),
                    allowResize: true,
                    inlineEdit: 'number',
                    width: '250px'
                },
                {
                    property: 'stock',
                    dataIndex: 'stock',
                    label: this.$tc('sw-product.variations.generatedListColumnStock'),
                    allowResize: true,
                    inlineEdit: 'number',
                    width: '125px',
                    align: 'right'
                },
                {
                    property: 'productNumber',
                    dataIndex: 'productNumber',
                    label: this.$tc('sw-product.variations.generatedListColumnProductNumber'),
                    allowResize: true,
                    inlineEdit: 'string'
                }
            ];
        },

        getList() {
            // Promise needed for inline edit error handling
            return new Promise((resolve) => {
                this.isLoading = true;

                // Get criteria for search and for option sorting
                const searchCriteria = new Criteria();
                const optionCriteria = new Criteria();

                // Option sorting
                optionCriteria
                    .setLimit(500)
                    .addSorting(Criteria.sort('groupId'))
                    .addSorting(Criteria.sort('id'));

                // Criteria for Search
                searchCriteria.setTotalCountMode(1);
                searchCriteria
                    .setPage(this.page)
                    .setLimit(this.limit)
                    .addFilter(Criteria.equals('product.parentId', this.product.id))
                    .addAssociation('options', optionCriteria);

                // Add search term
                this.buildSearchQuery(searchCriteria);

                // User selected filters
                if (this.getFilterCriteria()) {
                    this.getFilterCriteria().forEach((criteria) => {
                        searchCriteria.addFilter(criteria);
                    });
                }

                // check for other sort values
                if (!this.$route.query.sortBy || this.$route.query.sortBy === 'name') {
                    searchCriteria
                        .addSorting(Criteria.sort('product.options.groupId', this.sortDirection))
                        .addSorting(Criteria.sort('product.options.id', this.sortDirection));
                } else {
                    searchCriteria
                        .addSorting(Criteria.sort(this.sortBy, this.sortDirection));
                }

                // Start search
                this.productRepository
                    .search(searchCriteria, this.context)
                    .then((res) => {
                        this.total = res.total;
                        this.variantList = res.items;
                        this.isLoading = false;
                        this.$emit('variants-updated', this.variantList);
                        resolve();
                    });
            });
        },

        buildSearchQuery(criteria) {
            if (!this.term) {
                return criteria;
            }

            // Split each word for search
            const terms = this.term.split(' ');

            // Create query for each single word
            terms.forEach((term) => {
                criteria.addQuery(Criteria.equals('product.options.name', term), 3500);
                criteria.addQuery(Criteria.contains('product.options.name', term), 500);
            });

            // return the input
            return criteria;
        },

        getFilterOptions() {
            // Prepare groups
            const groups = [...this.selectedGroups]
                .sort((a, b) => a.position - b.position).map((group, index) => {
                    const children = this.getOptionsForGroup(group.id);

                    return {
                        id: group.id,
                        name: group.name,
                        childCount: children.length,
                        parentId: null,
                        afterId: index > 0 ? this.selectedGroups[index - 1].id : null,
                        storeObject: group
                    };
                });

            // Prepare options
            const children = groups.reduce((result, group) => {
                const options = this.getOptionsForGroup(group.id);

                // Iterate for each group options
                const optionsForGroup = options.sort((elementA, elementB) => {
                    return elementA.position - elementB.position;
                }).map((element, index) => {
                    const option = element.option;

                    // Get previous element
                    let afterId = null;
                    if (index > 0) {
                        afterId = options[index - 1].option.id;
                    }

                    return {
                        id: option.id,
                        name: option.name,
                        childCount: 0,
                        parentId: option.groupId,
                        afterId,
                        storeObject: element
                    };
                });

                return [...result, ...optionsForGroup];
            }, []);

            // Assign groups and children to order objects
            this.filterOptions = [...groups, ...children];
        },

        resetFilterOptions() {
            this.filterOptions = [];
            this.includeOptions = [];

            this.$nextTick(() => {
                this.getFilterOptions();
                this.getList();
            });
        },

        filterOptionChecked(option) {
            if (option.checked) {
                // Remove from include list
                this.includeOptions.push({
                    id: option.id,
                    groupId: option.parentId
                });
            } else {
                // Add to include option list
                this.includeOptions = this.includeOptions.filter((includeOption) => includeOption.id !== option.id);
            }
        },

        getFilterCriteria() {
            if (this.includeOptions.length <= 0) {
                return false;
            }

            // Collect each selected option in a group
            // [
            //   {id: 'abc123', options: [...optionIds]},
            //   {id: 'def456', options: [...optionIds]},
            // ]
            const optionInGroups = this.includeOptions.reduce((result, option) => {
                const parentGroup = result.find((group) => group.id === option.groupId);

                // Push to group when array exists
                if (parentGroup) {
                    parentGroup.options.push(option.id);
                } else {
                    // otherwise create new group with the option
                    result.push({
                        id: option.groupId,
                        options: [option.id]
                    });
                }

                return result;
            }, []);

            return optionInGroups.map((group) => {
                return Criteria.equalsAny('product.optionIds', group.options);
            });
        },

        getOptionsForGroup(groupId) {
            return Object.values(this.product.configuratorSettings.items).filter((element) => {
                return !element.isDeleted && element.option.groupId === groupId;
            });
        },

        onVariationDelete(item) {
            this.showDeleteModal = item.id;
        },

        onInlineEditSave(variation) {
            this.productRepository.save(variation, this.context).then(() => {
                this.getList().then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-product.variations.generatedListTitleSaveSuccess'),
                        message: this.$tc('sw-product.variations.generatedListMessageSaveSuccess')
                    });
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-product.variations.generatedListTitleSaveError'),
                    message: this.$tc('sw-product.variations.generatedListMessageSaveError')
                });
            });
        },

        onInlineEditCancel() {
            this.getList();
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(item) {
            this.modalLoading = true;
            this.showDeleteModal = false;

            this.productRepository.delete(item.id, this.context).then(() => {
                this.modalLoading = false;

                this.createNotificationSuccess({
                    title: this.$tc('sw-product.variations.generatedListTitleDeleteError'),
                    message: this.$tc('sw-product.variations.generatedListMessageDeleteSuccess')
                });

                this.getList();
            });
        },

        isPriceEditing(value) {
            this.priceEdit = value;
        }
    }
});
