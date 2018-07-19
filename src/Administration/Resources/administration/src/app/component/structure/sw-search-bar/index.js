import { Component } from 'src/core/shopware';
import './sw-search-bar.less';
import template from './sw-search-bar.html.twig';

Component.register('sw-search-bar', {
    inject: ['searchService'],

    template,

    data() {
        return {
            isSearchOpened: false,
            results: []
        };
    },

    computed: {
        searchResults() {
            return this.results;
        }
    },

    methods: {
        openSearchSuggestions(searchQuery) {
            if (searchQuery && searchQuery.length) {
                console.log(searchQuery);
                this.searchService.search({ term: searchQuery }).then((response) => {
                    this.results = response.data;
                    console.log(response.data);
                });
                this.isSearchOpened = true;
            } else {
                this.isSearchOpened = false;
            }

            // this.searchService.search(searchQuery).then((response) => {
            //     console.log(response.data);
            //     this.results = response.data;
            // });
        }
    }
});
