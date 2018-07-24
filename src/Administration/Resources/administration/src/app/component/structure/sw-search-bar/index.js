import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-search-bar.html.twig';
import './sw-search-bar.less';

Component.register('sw-search-bar', {
    inject: ['searchService'],

    template,

    data() {
        return {
            showResultsContainer: false,
            useSearchTypeWhenSet: true,
            searchTerm: '',
            results: [],
            isActive: false
        };
    },

    computed: {
        searchResults() {
            return this.results;
        },

        searchTypeColor() {
            if (!this.$route.meta.$module) {
                return false;
            }

            return {
                'background-color': this.$route.meta.$module.color
            };
        },

        useTypeSearch() {
            return !!(this.$slots['search-type'] && this.useSearchTypeWhenSet);
        },

        showSearchResults() {
            return this.showResultsContainer && !this.useTypeSearch;
        }
    },

    methods: {
        tc(name) {
            return this.$tc(name);
        },

        onFocusInput() {
            this.isActive = true;
            if (this.useTypeSearch) {
                return;
            }

            this.showResultsContainer = true;
        },

        onBlur() {
            this.isActive = false;
            this.showResultsContainer = false;
        },

        onSearchTermChange() {
            if (this.useTypeSearch) {
                this.doListSearch();
            } else {
                this.doGlobalSearch();
            }
        },

        onKeydownInput(key) {
            if (this.searchTerm.length === 0 && key.code === 'Backspace') {
                this.useSearchTypeWhenSet = false;
            }
        },

        doListSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            this.$root.$emit('search', searchTerm);
        }, 400),

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            this.isLoading = true;
            if (searchTerm && searchTerm.length > 0) {
                this.loadResults(searchTerm);
            } else {
                this.showResultsContainer = false;
                this.loadPreviewResults();
                // this.scrollToResultsTop();
            }
        }, 400),

        loadResults(searchTerm) {
            this.searchService.search({ term: searchTerm }).then((response) => {
                this.results = response.data;
                this.isLoading = false;
            });
            this.showResultsContainer = true;
        },

        loadPreviewResults() {
            console.log('test');
        }
    }
});
