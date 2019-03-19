import { Application } from 'src/core/shopware';
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

    inject: ['searchService', 'searchTypeService'],

    props: {
        initialSearchType: {
            type: String,
            required: false,
            default: ''
        },
        typeSearchAlwaysInContainer: {
            type: Boolean,
            required: false,
            default: false
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
            currentSearchType: this.initialSearchType,
            showResultsContainer: false,
            searchTerm: this.initialSearch,
            results: [],
            isActive: false,
            isOffCanvasShown: false,
            isSearchBarShown: false,
            activeResultIndex: 0,
            activeResultColumn: 0,
            activeTypeListIndex: 0,
            isLoading: false,
            inputHovered: false,
            searchTypes: null,
            showTypeSelectContainer: false,
            typeSelectResults: []
        };
    },

    watch: {
        // Watch for changes in query parameters
        '$route'(newValue) {
            // Use type search again when route changes and the term is undefined
            if (newValue.query.term === undefined && this.initialSearchType) {
                this.currentSearchType = this.initialSearchType;
            }

            this.searchTerm = newValue.query.term ? newValue.query.term : '';
        }
    },

    computed: {
        searchBarFieldClasses() {
            return {
                'is--active': this.isActive
            };
        },

        placeholderSearchInput() {
            let placeholder = this.$tc('global.sw-search-bar.placeholderSearchField');

            if (this.currentSearchType) {
                placeholder = this.$tc(this.searchTypes[this.currentSearchType].placeholderSnippet);
            }

            if (this.inputHovered) {
                return `${placeholder} ${this.$tc('global.sw-search-bar.placeholderShortcutInfo')}`;
            }

            return placeholder;
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
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

            if (this.$route.query.term) {
                this.searchTerm = this.$route.query.term;
            }

            this.searchTypes = this.searchTypeService.getTypes();

            this.registerListener();
        },

        destroyedComponent() {
            document.removeEventListener('click', this.closeOnClickOutside);
            document.removeEventListener('keydown', this.onKeyDown);
        },

        registerListener() {
            document.addEventListener('click', this.closeOnClickOutside);
            document.addEventListener('keydown', this.onKeyDown);
            this.$on('sw-search-bar-item-mouse-over', this.setActiveResultPosition);
        },

        getLabelSearchType(type) {
            if (!type) {
                type = this.currentSearchType;
            }

            const label = this.$tc(`global.entities.${type}`, 2);

            return label || this.currentSearchType;
        },

        onKeyDown(event) {
            if (event instanceof KeyboardEvent && event.key === 's') {
                const element = event.path[0];
                if (element.nodeName === 'INPUT') {
                    return;
                }
                if (element.nodeName === 'DIV' && element.className.includes('ql-editor')) {
                    return;
                }

                this.$refs.searchInput.focus();
                event.preventDefault();
            }
        },

        closeOnClickOutside(event) {
            const target = event.target;

            if (!target.closest('.sw-search-bar')) {
                this.clearSearchTerm();
            }
        },

        clearSearchTerm() {
            this.searchTerm = '';
            this.showResultsContainer = false;
            this.activeResultPosition = 0;

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
            const match = this.searchTerm.match(/^#(.*)/);
            if (match !== null) {
                this.showTypeSelectContainer = true;
                this.showResultsContainer = false;
                this.activeTypeListIndex = 0;
                this.filterTypeSelectResults(match[1]);

                return;
            }

            this.showTypeSelectContainer = false;

            if (this.typeSearchAlwaysInContainer) {
                this.doListSearchWithContainer();
                return;
            }

            if (!this.initialSearchType && this.currentSearchType) {
                this.doListSearchWithContainer();
                return;
            }

            if (this.initialSearchType && this.currentSearchType && this.initialSearchType !== this.currentSearchType) {
                this.doListSearchWithContainer();
                return;
            }

            if (this.currentSearchType) {
                this.doListSearch();
                return;
            }

            this.doGlobalSearch();
        },

        filterTypeSelectResults(term) {
            this.typeSelectResults = [];

            Object.keys(this.searchTypes).forEach(key => {
                const snippet = this.$tc(`global.entities.${this.searchTypes[key].entityName}`, 2);
                if (snippet.toLowerCase().includes(term.toLowerCase()) || term === '') {
                    this.typeSelectResults.push(this.searchTypes[key]);
                }
            });
        },

        onClickType(type) {
            this.setSearchType(type);
        },

        setSearchType(type) {
            this.currentSearchType = type;
            this.showTypeSelectContainer = false;
            this.searchTerm = '';
        },

        toggleOffCanvas() {
            this.isOffCanvasShown = !this.isOffCanvasShown;

            this.$root.$emit('toggleOffCanvas', this.isOffCanvasShown);
        },

        resetSearchType() {
            if (this.searchTerm.length === 0) {
                this.currentSearchType = null;
            }
        },

        doListSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            this.$emit('search', searchTerm);
        }, 400),

        doListSearchWithContainer: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            if (searchTerm && searchTerm.length > 0) {
                this.loadTypeSearchResults(searchTerm);
            } else {
                this.showResultsContainer = false;
            }
        }, 400),

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            if (searchTerm && searchTerm.length > 0) {
                this.loadResults(searchTerm);
            } else {
                this.showResultsContainer = false;
            }
        }, 400),

        loadResults(searchTerm) {
            this.isLoading = true;
            this.results = [];
            this.searchService.search({ term: searchTerm }).then((response) => {
                this.results = response.data;
                this.activeResultColumn = 0;
                this.activeResultIndex = 0;
                this.isLoading = false;
            });
            if (!this.showTypeSelectContainer) {
                this.showResultsContainer = true;
            }
        },

        loadTypeSearchResults(searchTerm) {
            this.isLoading = true;
            const params = {
                limit: 10,
                term: searchTerm
            };
            this.results = [];
            const entityResults = {};
            const apiServiceName = this.searchTypes[this.currentSearchType].entityService;
            if (!Application.getContainer('factory').apiService.has(apiServiceName)) {
                // Todo Throw error here
                return;
            }

            const apiService = Application.getContainer('factory').apiService.getByName(apiServiceName);

            apiService.getList(params).then((response) => {
                entityResults.total = response.data.length;
                entityResults.entity = this.currentSearchType;
                entityResults.entities = response.data;

                this.results.push(entityResults);

                this.isLoading = false;
            });
            if (!this.showTypeSelectContainer) {
                this.showResultsContainer = true;
            }
        },

        setActiveResultPosition({ index, column }) {
            this.activeResultIndex = index;
            this.activeResultColumn = column;
            this.emitActiveResultPosition();
        },

        emitActiveResultPosition() {
            this.$emit('sw-search-bar-active-item-index', {
                index: this.activeResultIndex,
                column: this.activeResultColumn
            });
        },

        navigateLeftResults() {
            if (this.showTypeSelectContainer) {
                if (this.activeTypeListIndex !== 0) {
                    this.activeTypeListIndex -= 1;
                }
            }

            if (!this.showResultsContainer) {
                return;
            }

            if (this.activeResultColumn > 0) {
                this.activeResultColumn = this.activeResultColumn - 1;
                const itemsInColumn = this.results[this.activeResultColumn].entities.length;
                if (this.activeResultIndex + 1 > itemsInColumn) {
                    this.activeResultIndex = itemsInColumn - 1;
                }
            }

            this.setActiveResultPosition({ index: this.activeResultIndex, column: this.activeResultColumn });
            this.checkScrollPosition();
        },

        navigateRightResults() {
            if (this.showTypeSelectContainer) {
                if (this.activeTypeListIndex !== this.typeSelectResults.length - 1) {
                    this.activeTypeListIndex += 1;
                }
            }

            if (!this.showResultsContainer) {
                return;
            }

            if (this.activeResultColumn < this.results.length - 1) {
                this.activeResultColumn = this.activeResultColumn + 1;
                const itemsInColumn = this.results[this.activeResultColumn].entities.length;
                if (this.activeResultIndex + 1 > itemsInColumn) {
                    this.activeResultIndex = itemsInColumn - 1;
                }
            }

            this.setActiveResultPosition({ index: this.activeResultIndex, column: this.activeResultColumn });
            this.checkScrollPosition();
        },

        navigateUpResults() {
            if (this.showTypeSelectContainer) {
                if (this.activeTypeListIndex !== 0) {
                    this.activeTypeListIndex -= 1;
                }
            }

            if (!this.showResultsContainer) {
                return;
            }

            if (this.activeResultIndex === 0) {
                if (this.activeResultColumn > 0) {
                    this.activeResultColumn = this.activeResultColumn - 1;
                    const itemsInNewColumn = this.results[this.activeResultColumn].entities.length;
                    this.activeResultIndex = itemsInNewColumn - 1;
                }
            } else {
                this.activeResultIndex -= 1;
            }

            this.setActiveResultPosition({ index: this.activeResultIndex, column: this.activeResultColumn });
            this.checkScrollPosition();
        },

        navigateDownResults() {
            if (this.showTypeSelectContainer) {
                if (this.activeTypeListIndex !== this.typeSelectResults.length - 1) {
                    this.activeTypeListIndex += 1;
                }
            }

            if (!this.showResultsContainer) {
                return;
            }

            const itemsInActualColumn = this.results[this.activeResultColumn].entities.length;

            if (this.activeResultIndex === itemsInActualColumn - 1 || itemsInActualColumn < 1) {
                if (this.activeResultColumn < this.results.length - 1) {
                    this.activeResultColumn = this.activeResultColumn + 1;
                    this.activeResultIndex = 0;
                }
            } else {
                this.activeResultIndex += 1;
            }

            this.setActiveResultPosition({ index: this.activeResultIndex, column: this.activeResultColumn });
            this.checkScrollPosition();
        },

        checkScrollPosition() {
            // Wait for the next render tick because we need the new active item
            this.$nextTick(() => {
                const resultsContainer = this.$refs.resultsContainer;
                const activeItem = resultsContainer.querySelector('.is--active');
                const itemHeight = resultsContainer.querySelector('.sw-search-bar-item').offsetHeight;

                const resultContainerHeight = resultsContainer.offsetHeight;
                const activeItemPosition = activeItem.offsetTop + itemHeight;

                if (activeItemPosition + itemHeight * 2 > resultContainerHeight + resultsContainer.scrollTop) {
                    resultsContainer.scrollTop = activeItemPosition + itemHeight * 2 - resultContainerHeight;
                } else if (activeItemPosition - itemHeight * 3 < resultsContainer.scrollTop) {
                    resultsContainer.scrollTop = activeItemPosition - itemHeight * 3;
                }
            });
        },

        onKeyUpEnter() {
            this.$emit('sw-search-bar-on-keyup-enter', this.activeResultIndex, this.activeResultColumn);

            if (this.showTypeSelectContainer) {
                if (this.typeSelectResults.length > 0) {
                    this.setSearchType(this.typeSelectResults[this.activeTypeListIndex].entityName);
                }
            }
        },

        onMouseEnter() {
            this.inputHovered = true;
        },

        onMouseLeave() {
            this.inputHovered = false;
        },

        getSearchTypeProperty(entityName, propertyName) {
            if (!this.searchTypes[entityName] || !this.searchTypes[entityName].hasOwnProperty(propertyName)) {
                return '';
            }
            return this.searchTypes[entityName][propertyName];
        },

        isResultEmpty() {
            return !this.results.some(result => result.total !== 0);
        },

        onMouseEnterSearchType(index) {
            this.activeTypeListIndex = index;
        }
    }
};
