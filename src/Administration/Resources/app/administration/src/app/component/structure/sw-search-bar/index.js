import template from './sw-search-bar.html.twig';
import './sw-search-bar.scss';

const { Component, Application } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

/**
 * @public
 * @description
 * Renders the search bar. This component uses the search service to find entities in the administration.
 * @status ready
 * @example-type code-only
 */
Component.register('sw-search-bar', {
    template,

    inject: [
        'searchService',
        'searchTypeService',
        'repositoryFactory',
    ],

    shortcuts: {
        f: 'setFocus',
    },

    props: {
        initialSearchType: {
            type: String,
            required: false,
            default: '',
        },
        typeSearchAlwaysInContainer: {
            type: Boolean,
            required: false,
            default: false,
        },
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        initialSearch: {
            type: String,
            required: false,
            default: '',
        },
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
            searchTypes: null,
            showTypeSelectContainer: false,
            typeSelectResults: [],
            moduleFactory: {},
        };
    },

    computed: {
        searchBarFieldClasses() {
            return {
                'is--active': this.isActive,
            };
        },

        placeholderSearchInput() {
            let placeholder = this.$tc('global.sw-search-bar.placeholderSearchField');

            if (this.currentSearchType) {
                if (this.placeholder !== '') {
                    placeholder = this.placeholder;
                } else if (Object.keys(this.searchTypes).includes(this.currentSearchType)) {
                    placeholder = this.$tc(this.searchTypes[this.currentSearchType].placeholderSnippet);
                }
            }

            return placeholder;
        },
    },

    watch: {
        // Watch for changes in query parameters
        '$route'(newValue) {
            // Use type search again when route changes and the term is undefined
            if (newValue.query.term === undefined && this.initialSearchType) {
                this.currentSearchType = this.initialSearchType;
            }

            // Do not modify the search term when the user is currently typing
            if (this.isActive) {
                return;
            }

            this.searchTerm = newValue.query.term ? newValue.query.term : '';
        },
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
                component: this,
            });

            if (this.$route.query.term) {
                this.searchTerm = this.$route.query.term;
            }

            this.searchTypes = this.searchTypeService.getTypes();
            this.typeSelectResults = Object.values(this.searchTypes);

            this.registerListener();

            this.moduleFactory = Application.getContainer('factory').module;
        },

        destroyedComponent() {
            document.removeEventListener('click', this.closeOnClickOutside);
        },

        registerListener() {
            document.addEventListener('click', this.closeOnClickOutside);
            this.$on('mouse-over', this.setActiveResultPosition);
        },

        getLabelSearchType(type) {
            if (!type) {
                type = this.currentSearchType;
            }

            if (!this.$te((`global.entities.${type}`))) {
                return this.currentSearchType;
            }

            return this.$tc(`global.entities.${type}`, 2);
        },

        setFocus() {
            this.$refs.searchInput.focus();
        },

        closeOnClickOutside(event) {
            const target = event.target;

            if (!target.closest('.sw-search-bar')) {
                this.clearSearchTerm();
                this.showTypeSelectContainer = false;
            }
        },

        clearSearchTerm() {
            this.showResultsContainer = false;
            this.activeResultPosition = 0;
        },

        onFocusInput() {
            this.isActive = true;
            if (!this.searchTerm) {
                this.showTypeContainer();
            } else if (
                this.currentSearchType !== this.initialSearchType ||
                this.currentSearchType.length <= 0
            ) {
                this.showResultsContainer = true;
            }
        },

        onBlur() {
            this.isActive = false;
        },

        showSearchBar() {
            this.isSearchBarShown = true;
            this.isActive = true;
            this.isOffCanvasShown = false;

            this.$root.$emit('toggle-offcanvas', this.isOffCanvasShown);
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
                this.showTypeContainer();
                this.filterTypeSelectResults(match[1]);

                return;
            }

            this.showTypeSelectContainer = false;

            if (this.typeSearchAlwaysInContainer && this.currentSearchType) {
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

        showTypeContainer() {
            this.showTypeSelectContainer = true;
            this.showResultsContainer = false;
            this.activeTypeListIndex = 0;
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

            this.$root.$emit('toggle-offcanvas', this.isOffCanvasShown);
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
                response.data.forEach((item) => {
                    item.entities = Object.values(item.entities);
                });

                this.results = response.data.filter(item => this.searchTypes.hasOwnProperty(item.entity) && item.total > 0);
                this.activeResultColumn = 0;
                this.activeResultIndex = 0;
                this.isLoading = false;
            });
            if (!this.showTypeSelectContainer) {
                this.showResultsContainer = true;
            }
        },

        loadTypeSearchResults(searchTerm) {
            // If searchType has an "entityService" load by service, otherwise load by entity
            if (this.searchTypes[this.currentSearchType].entityService) {
                this.loadTypeSearchResultsByService(searchTerm);
                return;
            }

            this.isLoading = true;
            this.results = [];
            const entityResults = {};

            const entityName = this.searchTypes[this.currentSearchType].entityName;
            const repository = this.repositoryFactory.create(entityName);
            const criteria = new Criteria();

            criteria.setTerm(searchTerm);

            repository.search(criteria, Shopware.Context.api).then((response) => {
                entityResults.total = response.total;
                entityResults.entity = this.currentSearchType;
                entityResults.entities = response;

                this.results.push(entityResults);
                this.isLoading = false;
            });
            if (!this.showTypeSelectContainer) {
                this.showResultsContainer = true;
            }
        },

        loadTypeSearchResultsByService(searchTerm) {
            this.isLoading = true;
            const params = {
                limit: 25,
                term: searchTerm,
            };
            this.results = [];
            const entityResults = {};
            const apiServiceName = this.searchTypes[this.currentSearchType].entityService;
            if (!Application.getContainer('factory').apiService.has(apiServiceName)) {
                throw new Error(`sw-search-bar - Api service ${apiServiceName} not found`);
            }

            const apiService = Application.getContainer('factory').apiService.getByName(apiServiceName);

            apiService.getList(params).then((response) => {
                entityResults.total = response.meta.total;
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
            this.$emit('active-item-index-select', {
                index: this.activeResultIndex,
                column: this.activeResultColumn,
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
                this.activeResultColumn -= 1;
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
                this.activeResultColumn += 1;
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
                    this.activeResultColumn -= 1;
                    const itemsInNewColumn = Object.keys(this.results[this.activeResultColumn].entities).length;
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
                    // Move to the next column if it exists
                    if (this.results[this.activeResultColumn + 1]) {
                        this.activeResultColumn += 1;
                        this.activeResultIndex = 0;
                    } else {
                        return;
                    }
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
            this.$emit('keyup-enter', this.activeResultIndex, this.activeResultColumn);

            if (this.showTypeSelectContainer) {
                if (this.typeSelectResults.length > 0) {
                    this.setSearchType(this.typeSelectResults[this.activeTypeListIndex].entityName);
                }
            }
        },

        getSearchTypeProperty(entityName, propertyName) {
            if (!this.searchTypes[entityName] || !this.searchTypes[entityName].hasOwnProperty(propertyName)) {
                return '';
            }
            return this.searchTypes[entityName][propertyName];
        },

        getEntityIconName(entityName) {
            const module = this.moduleFactory.getModuleByEntityName(entityName);

            if (!module) {
                return 'default-object-books';
            }

            return module.manifest.icon || entityName;
        },

        getEntityIconColor(entityName) {
            const module = this.moduleFactory.getModuleByEntityName(entityName);

            if (!module) {
                return '#AEC4DA';
            }

            return module.manifest.color || '#AEC4DA';
        },

        isResultEmpty() {
            return !this.results.some(result => result.total !== 0);
        },

        onMouseEnterSearchType(index) {
            this.activeTypeListIndex = index;
        },
    },
});
