import { Component, Mixin } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-catalog-detail.html.twig';
import './sw-catalog-detail.less';

Component.register('sw-catalog-detail', {
    template,

    mixins: [
        Mixin.getByName('catalog')
    ],

    data() {
        return {
            addCategoryName: '',
            categories: []
        };
    },

    created() {
        if (this.$route.params.id) {
            this.catalogId = this.$route.params.id;
        }
    },

    mounted() {
        this.getCategories();
    },

    methods: {
        onAddCategory() {
            if (!this.addCategoryName.length || this.addCategoryName.length <= 0) {
                return;
            }

            this.$store.dispatch('category/createEmpty').then((newCategory) => {
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
            });
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

            params.criteria = [CriteriaFactory.nested('AND', ...criteria).getQuery()];

            if (searchTerm !== null) {
                params.term = searchTerm;
            }

            this.isLoading = searchTerm !== null || parentId === null;

            return this.$store.dispatch('category/getList', params).then((data) => {
                data.items.forEach((category) => {
                    if (typeof this.categories.find(i => i.d === category.id) !== 'undefined') {
                        return;
                    }

                    this.categories.push(category);
                });

                this.isLoading = false;

                return data.items;
            });
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
            const categoryCue = this.getCatalogCategoryCue();
            this.isLoading = true;

            return Promise.all(categoryCue).then(() => {
                this.isLoading = false;
                this.saveCatalog();
            }).catch((exception) => {
                if (exception.response.data && exception.response.data.errors) {
                    exception.response.data.errors.forEach((error) => {
                        this.$store.commit('error/addError', {
                            module: 'catalog',
                            error
                        });
                    });
                }

                this.isLoading = false;
            });
        },

        getCatalogCategoryCue() {
            const categoryState = this.$store.state.category.draft;
            const categoryCue = [];

            Object.keys(categoryState).forEach((key) => {
                if (categoryState[key].catalogId === this.catalog.id) {
                    categoryCue.push(new Promise((resolve, reject) => {
                        this.$store.dispatch('category/saveItem', categoryState[key])
                            .then((response) => {
                                resolve(response);
                            })
                            .catch((response) => {
                                reject(response);
                            });
                    }));
                }
            });

            return categoryCue;
        }
    }
});
