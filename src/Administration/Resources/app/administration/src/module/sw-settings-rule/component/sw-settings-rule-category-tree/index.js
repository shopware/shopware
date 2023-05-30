import template from './sw-settings-rule-category-tree.html.twig';
import './sw-settings-rule-category-tree.scss';

const { Criteria } = Shopware.Data;

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    inject: ['repositoryFactory'],

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
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
        hideSearch: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            categories: [],
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

        hasItemAssociation(item) {
            return item[this.association]?.length > 0 || item.extensions[this.association]?.length > 0;
        },

        getTreeItems(parentId = null, term = null, withTermFilter = false) {
            this.isFetching = true;

            const categoryCriteria = this.treeCriteria;

            categoryCriteria.filters = categoryCriteria.filters.filter((filter) => {
                if (filter.type === 'equals' && filter.field === 'parentId') {
                    return false;
                }

                if (filter.type === 'contains' && filter.field === 'name') {
                    return false;
                }

                return true;
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
