import { Component } from 'src/core/shopware';
import template from './sw-category-select.html.twig';
import utils from './../../../../../src/core/service/util.service';
import './sw-category-select.scss';

Component.extend('sw-category-select', 'sw-select', {
    template,

    methods: {
        addSelection({ item }) {
            this.toggleSelection(item);
        },

        isInSelections() {
            return false;
        },

        dismissSelection(item) {
            this.toggleSelection(item);
        },

        toggleSelection(item) {
            this.$emit('sw-category-select-on-select', item[this.itemValueKey]);
        },

        getResults() {
            if (this.searchTerm.length < 3) {
                return;
            }

            const params = {
                page: 1,
                limit: 25,
                term: this.searchTerm,
                criteria: this.criteria
            };

            this.isLoading = true;
            this.results = [];

            this.store.getList(params).then(response => {
                this.results = response.items;
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });
        },

        openResultList() {
            if (this.isExpanded === false) {
                this.loadPreviewResults();
            }
            this.isExpanded = true;
            this.emitActiveResultPosition();
        },

        // @todo
        doGlobalSearch: utils.debounce(function debouncedSearch() {
            this.getResults();
            this.loadSelections();
            this.scrollToResultsTop();
        }, 400),

        // override functions that get data
        loadResults() {
            this.getResults();
        },

        loadPreviewResults() {
            this.getResults();
        },

        // override methods as empty
        loadSelections() {},
        emitChanges() {}
    }
});
