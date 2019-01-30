import utils from 'src/core/service/util.service';
import template from './sw-search-bar.html.twig';
import './sw-search-bar.scss';

/**
 * @public
 * @description
 * Renders the search bar. This component uses the search service to find entities in the administration.
 * @status ready
 * @example-type code-only
 */
export default {
    name: 'sw-search-bar',
    template,

    inject: ['searchService'],

    props: {
        searchType: {
            type: String,
            required: false,
            default: ''
        },
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        initialSearch: {
            type: String,
            required: false,
            default: ''
        }
    },

    data() {
        return {
            showResultsContainer: false,
            useSearchTypeWhenSet: true,
            searchTerm: this.initialSearch,
            results: [],
            isActive: false,
            isOffCanvasShown: false,
            isSearchBarShown: false
        };
    },

    computed: {
        searchTypeColor() {
            if (!this.$route.meta.$module) {
                return false;
            }

            return {
                'background-color': this.$route.meta.$module.color
            };
        },

        useTypeSearch() {
            return this.searchType !== '' && this.useSearchTypeWhenSet;
        },

        showSearchResults() {
            return this.showResultsContainer && !this.useTypeSearch;
        },

        searchBarFieldClasses() {
            return {
                'is--active': this.isActive
            };
        },

        placeholderSearchInput() {
            if (this.useTypeSearch && this.placeholder !== '') {
                return this.placeholder;
            }

            return this.$tc('global.sw-search-bar.placeholderSearchField');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const that = this;

            this.showSearchFieldOnLargerViewports();

            this.$device.onResize({
                listener() {
                    that.showSearchFieldOnLargerViewports();
                },
                component: this
            });
        },

        clearSearchTerm() {
            this.searchTerm = '';
            this.showResultsContainer = false;

            this.results = [];
        },

        onFocusInput() {
            this.isActive = true;
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

        showSearchFieldOnLargerViewports() {
            if (this.$device.getViewportWidth() > 500) {
                this.isSearchBarShown = true;
            }
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
            this.$emit('search', searchTerm);
        }, 400),

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            this.isLoading = true;

            if (searchTerm && searchTerm.length > 0) {
                this.loadResults(searchTerm);
                window.addEventListener('click', this.clearSearchTerm, {
                    once: true
                });
            } else {
                this.showResultsContainer = false;
            }
        }, 400),

        loadResults(searchTerm) {
            this.results = [];
            this.searchService.search({ term: searchTerm }).then((response) => {
                this.results = response.data;
                this.isLoading = false;
            });
            this.showResultsContainer = true;
        }
    }
};
