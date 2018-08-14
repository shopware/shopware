import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-search-bar.html.twig';
import './sw-search-bar.less';

Component.register('sw-search-bar', {
    template,

    inject: ['searchService'],

    data() {
        return {
            showResultsContainer: false,
            useSearchTypeWhenSet: true,
            searchTerm: '',
            results: [],
            isActive: false,
            scrollbarOffset: 0,
            isOffCanvasShown: false,
            isSearchBarShown: false
        };
    },

    created() {
        const me = this;

        this.$device.onResize({
            listener() {
                if (me.$device.getViewportWidth() >= 500) {
                    me.isSearchBarShown = true;
                }
            },
            component: this
        });
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
        },

        searchBarStyles() {
            return {
                paddingRight: `${this.scrollbarOffset}px`
            };
        },

        searchBarFieldClasses() {
            return {
                'is--active': this.isActive
            };
        }
    },

    updated() {
        this.setScrollbarOffset();
    },

    methods: {
        onFocusInput() {
            this.isActive = true;
            if (this.useTypeSearch) {
                return;
            }

            this.showResultsContainer = true;
        },

        onBlur() {
            this.isActive = false;
        },

        showSearchBar() {
            this.isSearchBarShown = true;
            this.isActive = true;
            this.isOffCanvasShown = false;

            this.$root.$emit('toggleOffCanvas', this.isOffCanvasShown);
        },

        hideSearchBar() {
            this.isSearchBarShown = false;
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

        toggleOffCanvas() {
            this.isOffCanvasShown = !this.isOffCanvasShown;

            this.$root.$emit('toggleOffCanvas', this.isOffCanvasShown);
        },

        resetSearchType() {
            if (this.searchTerm.length === 0) {
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
            }
        }, 400),

        loadResults(searchTerm) {
            this.searchService.search({ term: searchTerm }).then((response) => {
                this.results = response.data;
                this.isLoading = false;
            });
            this.showResultsContainer = true;
        },

        setScrollbarOffset() {
            const swPageContent = document.querySelector('.sw-page__content').firstChild;
            this.scrollbarOffset = dom.getScrollbarWidth(swPageContent);
        }
    }
});
