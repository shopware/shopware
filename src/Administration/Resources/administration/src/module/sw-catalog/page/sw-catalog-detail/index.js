import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-catalog-detail.html.twig';
import './sw-catalog-detail.less';

Component.register('sw-catalog-detail', {
    template,

    data() {
        return {
            catalogId: null,
            catalog: {},
            categories: [],
            addCategoryName: '',
            isLoading: false
        };
    },

    computed: {
        catalogStore() {
            return State.getStore('catalog');
        },

        categoryStore() {
            return State.getStore('category');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.catalogId = this.$route.params.id;
                this.catalog = this.catalogStore.getById(this.catalogId);

                this.getCategories();
            }
        },

        getCategories(parentId = null, searchTerm = null) {
            const criteria = [];
            const params = {
                limit: 100
            };

            criteria.push(CriteriaFactory.term('catalogId', this.catalogId));

            if (parentId !== false) {
                criteria.push(CriteriaFactory.term('parentId', parentId));
            }

            params.criteria = CriteriaFactory.nested('AND', ...criteria);

            if (searchTerm !== null) {
                params.term = searchTerm;
            }

            this.isLoading = searchTerm !== null || parentId === null;

            return this.categoryStore.getList(params).then((response) => {
                response.items.forEach((category) => {
                    if (typeof this.categories.find(i => i.d === category.id) !== 'undefined') {
                        return;
                    }

                    this.categories.push(category);
                });

                this.isLoading = false;
                return response.items;
            });
        },

        onAddCategory() {
            if (!this.addCategoryName.length || this.addCategoryName.length <= 0) {
                return;
            }

            const newCategory = this.categoryStore.create();

            newCategory.name = this.addCategoryName;
            newCategory.catalogId = this.catalogId;
            newCategory.parentId = null;
            newCategory.position = 0;

            this.categories.forEach((category) => {
                if (category.parentId === null) {
                    category.position += 1;
                }
            });

            this.categories.push(newCategory);
            this.addCategoryName = '';
        },

        searchCategories(searchTerm) {
            let parentId = false;

            if (searchTerm === null || !searchTerm.length) {
                parentId = null;
            }

            this.categories = [];
            return this.getCategories(parentId, searchTerm);
        },

        onSave() {
            this.isLoading = true;

            return this.categoryStore.sync().then(() => {
                return this.catalog.save().then(() => {
                    this.isLoading = false;
                });
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
