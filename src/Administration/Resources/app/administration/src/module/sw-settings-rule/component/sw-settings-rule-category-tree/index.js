import template from './sw-settings-rule-category-tree.html.twig';
import './sw-settings-rule-category-tree.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 * @package services-settings
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: ['on-selection'],

    props: {
        rule: {
            type: Object,
            required: true,
        },
        association: {
            type: String,
            required: true,
        },
        categoriesCollection: {
            type: Array,
            required: true,
        },
        hideHeadline: {
            type: Boolean,
            required: false,
            default: false,
        },
        hideSearch: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            categories: [],
            isComponentReady: false,
            isFetching: false,
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        treeCriteria() {
            const categoryCriteria = new Criteria(1, 500);
            categoryCriteria.getAssociation(this.association).addFilter(Criteria.equals('id', this.rule.id));

            return categoryCriteria;
        },
    },

    watch: {
        categoriesCollection: {
            handler() {
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
    },

    methods: {
        searchTreeItems(term) {
            this.getTreeItems(null, term, true);
        },

        onCheckItem(checkedItems) {
            this.$emit('on-selection', checkedItems);
        },

        getTreeItems(parentId = null, term = null, withTermFilter = false) {
            this.isFetching = true;

            const categoryCriteria = this.treeCriteria;

            categoryCriteria.filters = categoryCriteria.filters.filter((filter) => {
                if (filter.type === 'equals' && filter.field === 'parentId') {
                    return false;
                }

                return !(filter.type === 'contains' && filter.field === 'name');
            });

            if (term && withTermFilter) {
                categoryCriteria.addFilter(Criteria.contains('name', term));
            }

            if (!term) {
                categoryCriteria.addFilter(Criteria.equals('parentId', parentId));
            }

            // search for categories
            return this.categoryRepository.search(categoryCriteria, Shopware.Context.api).then((searchResult) => {
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
    },
};
