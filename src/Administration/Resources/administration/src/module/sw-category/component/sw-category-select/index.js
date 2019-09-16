import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-category-select.html.twig';
import './sw-category-select.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

Component.extend('sw-category-select', 'sw-select', {
    template,

    props: {
        categoryId: {
            type: String,
            required: true
        },

        assignedProducts: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            associations: [],
            originalAssociations: [],
            associationAddList: [],
            associationRemoveList: []
        };
    },

    watch: {
        '$route.params.id'() {
            this.resetAssociations();
        }
    },

    methods: {
        addSelection({ item }) {
            this.toggleSelection(item);
        },

        dismissSelection(item) {
            this.toggleSelection(item);
        },

        toggleSelection(item) {
            this.$emit('toggle-select', item[this.itemValueKey]);
        },

        getResults() {
            const params = {
                page: 1,
                limit: 25,
                term: this.searchTerm,
                criteria: this.criteria
            };

            this.isLoading = true;
            this.results = [];

            params.headers = { 'sw-inheritance': true };

            this.store.getList(params).then(response => {
                this.getAssociations(response.items).then(() => {
                    this.results = response.items;
                    this.isLoading = false;
                });
            });
        },

        getAssociations(results) {
            const ids = results.map(product => product.id);

            const params = {
                page: 1,
                limit: 25,
                criteria: CriteriaFactory.multi('AND',
                    CriteriaFactory.equalsAny('id', ids),
                    CriteriaFactory.equals('categories.id', this.categoryId))
            };

            return this.store.getList(params).then(response => {
                this.originalAssociations = response.items.map(item => item.id);
                this.combineAssociations();
            });
        },

        addToAssociations(productId) {
            if (this.findIndexInArray(this.associationAddList, productId) !== null) {
                this.associationAddList = this.removeFromArrayById(this.associationAddList, productId);
            } else if (this.findIndexInArray(this.associationRemoveList, productId) !== null) {
                this.associationRemoveList = this.removeFromArrayById(this.associationRemoveList, productId);
            } else {
                this.associationAddList.push(productId);
            }
            this.combineAssociations();
        },

        removeFromAssociations(productId) {
            if (this.findIndexInArray(this.associationRemoveList, productId) !== null) {
                this.associationRemoveList = this.removeFromArrayById(this.associationRemoveList, productId);
            } else if (this.findIndexInArray(this.associationAddList, productId) !== null) {
                this.associationAddList = this.removeFromArrayById(this.associationAddList, productId);
            } else {
                this.associationRemoveList.push(productId);
            }
            this.combineAssociations();
        },

        findIndexInArray(array, id) {
            const index = array.findIndex(entry => entry === id);
            return index !== -1 ? index : null;
        },

        removeFromArrayById(array, id, amount = 1) {
            const match = this.findIndexInArray(array, id);
            if (match !== -1) {
                array.splice(match, amount);
            }
            return array;
        },

        combineAssociations() {
            let result = [];
            result.push(...this.originalAssociations);
            result.push(...this.associationAddList);
            result = result.filter(id => !this.associationRemoveList.find(productId => productId === id));
            this.associations = result;
        },

        resetAssociations() {
            this.associations = [];
            this.originalAssociations = [];
            this.associationAddList = [];
            this.associationRemoveList = [];
        },

        checkAssociation(productId) {
            return this.associations.some(product => product === productId);
        },

        openResultList() {
            if (this.isExpanded === false) {
                this.loadPreviewResults();
            }
            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            this.getResults();
            this.loadSelected();
        }, 400),

        loadResults() {
            this.getResults();
        },

        loadPreviewResults() {
            this.getResults();
        },

        loadSelected() {},
        emitChanges() {},
        scrollToResultsTop() {}
    }
});
