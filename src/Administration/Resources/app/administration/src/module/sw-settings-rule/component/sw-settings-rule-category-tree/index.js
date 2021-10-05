import template from './sw-settings-rule-category-tree.html.twig';
import './sw-settings-rule-category-tree.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-rule-category-tree', {
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
            this.getTreeItems(null, term);
        },

        onCheckItem(checkedItems) {
            this.$emit('on-selection', checkedItems);
        },

        hasItemAssociation(item) {
            if (
                (item[this.association] && item[this.association].length > 0)
                || (item.extensions[this.association] && item.extensions[this.association].length > 0)
            ) {
                return true;
            }

            return false;
        },

        getTreeItems(parentId = null, term = null) {
            this.isFetching = true;

            // create criteria
            const categoryCriteria = new Criteria(1, 500);
            categoryCriteria.addAssociation(this.association);
            categoryCriteria.getAssociation(this.association).addFilter(Criteria.equals('id', this.rule.id));

            if (term !== null && term !== '') {
                categoryCriteria.addFilter(Criteria.contains('name', term));
            } else {
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
});
