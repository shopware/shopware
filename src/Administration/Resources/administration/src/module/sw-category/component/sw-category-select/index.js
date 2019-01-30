import { Component } from 'src/core/shopware';
import template from './sw-category-select.html.twig';
import utils from './../../../../../src/core/service/util.service';
import './sw-category-select.scss';

Component.extend('sw-category-select', 'sw-select', {
    template,

    // mixins: [
    //     Mixin.getByName('placeholder')
    // ],

    methods: {
        // emit a single value
        addSelection({ item }) {
            if (item === undefined || !item[this.itemValueKey]) {
                return;
            }
            // this.singleSelection = item;+
            this.$emit('input', item[this.itemValueKey]);
        },

        isInSelections(item) {
            console.log(item);
            return false;
            // if (this.multi) {
            //     return !this.selections.every((selection) => {
            //         return selection[this.itemValueKey] !== item[this.itemValueKey];
            //     });
            // }
            // return this.singleSelection[this.itemValueKey] === item[this.itemValueKey];
        },

        loadSelections() {
            this.isLoadingSelections = true;

            if (this.multi) {
                this.isLoadingSelections = true;

                this.associationStore.getList({
                    page: 1,
                    limit: 5 // ToDo: The concept of assigning a large amount of relations needs a special solution.
                }).then((response) => {
                    this.selections = response.items;
                    this.isLoadingSelections = false;
                });
            } else {
                // return if the value is not set yet(*note the watcher on value)
                if (!this.value) {
                    return;
                }
                this.singleSelection = this.store.getById(this.value);
            }
        },

        loadResults() {
            this.isLoading = true;
            // console.log(1, this.searchTerm);

            this.store.getList({
                page: 1,
                limit: 100,
                term: this.searchTerm,
                criteria: this.criteria
            }).then((response) => {
                this.results = response.items;
                // Reset active position index after search
                this.setActiveResultPosition({ index: 0 });
                this.scrollToResultsTop();
                // Finish loading after next render tick
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });
        },

        loadPreviewResults() {
            this.getResults();
            // this.isLoading = true;
            // this.results = [];
            // // console.log(2, this.searchTerm);

            // this.store.getList({
            //     page: 1,
            //     limit: 100,
            //     criteria: this.criteria
            // }).then((response) => {
            //     // Abort if a search is done atm
            //     if (this.searchTerm !== '') {
            //         return;
            //     }
            //     this.results = response.items;
            //     this.$nextTick(() => {
            //         this.isLoading = false;
            //     });
            // });
        },

        getResults() {
            this.isLoading = true;
            this.results = [];

            this.store.getList({ page: 1, limit: 100, criteria: this.criteria }).then(response => {
                if (this.searchTerm !== '') {
                    return;
                }
                this.results = response.items;
                this.$nextTick(() => {
                    this.isLoading = false;
                });
            });
        },

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            // console.log(4, this.searchTerm);
            if (this.searchTerm.length > 3) {
                this.loadResults();
            } else {
                this.loadPreviewResults();
                this.scrollToResultsTop();
            }
        }, 400),

        openResultList() {
            // console.log(5, this.searchTerm);
            if (this.isExpanded === false) {
                this.loadPreviewResults();
            }
            this.isExpanded = true;
            this.emitActiveResultPosition();
        }
    }
});
