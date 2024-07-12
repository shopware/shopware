import template from './sw-search-bar.html.twig';
import './sw-search-bar.scss';

const { Component, Application, Context } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;
const { cloneDeep } = utils.object;

/**
 * @package admin
 *
 * @private
 * @description
 * Renders the search bar. This component uses the search service to find entities in the administration.
 * @status ready
 * @example-type code-only
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-search-bar', {
    template,

    inject: [
        'searchService',
        'searchTypeService',
        'repositoryFactory',
        'acl',
        'feature',
        'searchRankingService',
        'userActivityApiService',
        'recentlySearchService',
    ],

    provide() {
        return {
            searchBarOnMouseOver: this.onMouseOver,
            searchBarRegisterActiveItemIndexSelectHandler: this.registerActiveItemIndexSelectHandler,
            searchBarUnregisterActiveItemIndexSelectHandler: this.unregisterActiveItemIndexSelectHandler,
            searchBarRegisterKeyupEnterHandler: this.registerKeyupEnterHandler,
            searchBarUnregisterKeyupEnterHandler: this.unregisterKeyupEnterHandler,
        };
    },

    shortcuts: {
        f: 'setFocus',
    },

    props: {
        /**
         * Determines if the initial search entity, e.g. for a search only in products, when entering its list
         */
        initialSearchType: {
            type: String,
            required: false,
            default: '',
        },
        /**
         * Forbids to search outside the defined search entity
         */
        typeSearchAlwaysInContainer: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: Context.app.adminEsEnable ?? false,
        },
        /**
         * Search bar placeholder
         */
        placeholder: {
            type: String,
            required: false,
            default: '',
        },
        /**
         * Preset search term
         */
        initialSearch: {
            type: String,
            required: false,
            default: '',
        },
        /**
         * Color of the entity tag in the search bar
         */
        entitySearchColor: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            currentSearchType: this.initialSearchType,
            showResultsContainer: false,
            showModuleFiltersContainer: false,
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
            salesChannelTypes: [],
            moduleFactory: Application.getContainer('factory').module || {},
            showResultsSearchTrends: false,
            resultsSearchTrends: [],
            showSearchPreferencesModal: false,
            searchLimit: 10,
            userSearchPreference: null,
            isComponentMounted: true,
            activeItemIndexSelectHandler: [],
            keyupEnterHandler: [],
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

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelTypeRepository() {
            return this.repositoryFactory.create('sales_channel_type');
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('type');

            return criteria;
        },

        canCreateSalesChannels() {
            return this.acl.can('sales_channel.creator');
        },

        moduleRegistry() {
            return this.moduleFactory.getModuleRegistry();
        },

        searchableModules() {
            const modules = [];

            this.moduleRegistry.forEach((module) => {
                const privilege = module.manifest.routes?.index?.meta?.privilege;

                if (!module.manifest?.title || (privilege && !this.acl.can(privilege))) {
                    return;
                }

                modules.push(module);
            });

            modules.sort((a, b) => a.manifest.name?.localeCompare(b.manifest.name));

            return modules;
        },

        criteriaCollection() {
            return {
                product: new Criteria(1, this.searchLimit + 1).addAssociation('options.group'),
            };
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        showSearchTipForEsSearch() {
            if (!this.adminEsEnable) {
                return false;
            }

            // This Regex matches the first word and space in the search term
            return this.searchTerm.match(/^[\w]+\s/);
        },

        adminEsEnable() {
            return Context.app.adminEsEnable ?? false;
        },
    },

    watch: {
        // Watch for changes in query parameters
        '$route'(newValue) {
            // Use type search again when route changes and the term is undefined
            if (this.isComponentMounted === true && newValue.query.term === undefined && this.initialSearchType) {
                this.currentSearchType = this.initialSearchType;
            }

            // Do not modify the search term when the user is currently typing
            if (this.isActive) {
                return;
            }

            this.searchTerm = newValue.query.term ? newValue.query.term : '';
        },

        '$route.name': {
            handler(to, from) {
                if (from === undefined || to === from) {
                    return;
                }

                this.resultsSearchTrends = [];
            },
            immediate: true,
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        async createdComponent() {
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
            this.typeSelectResults = Object.values(this.searchTypes).filter(searchType => !searchType.hideOnGlobalSearchBar);
            this.registerListener();

            this.userSearchPreference = await this.searchRankingService.getUserSearchPreference();

            if (this.canCreateSalesChannels) {
                await this.loadSalesChannelType();
            }
        },

        destroyedComponent() {
            document.removeEventListener('click', this.closeOnClickOutside);
        },

        registerListener() {
            document.addEventListener('click', this.closeOnClickOutside);

            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                this.$on('mouse-over', this.setActiveResultPosition);
            }
        },

        onMouseOver(index, column) {
            this.setActiveResultPosition({ index, column });
        },

        registerActiveItemIndexSelectHandler(handler) {
            this.activeItemIndexSelectHandler.push(handler);
        },

        unregisterActiveItemIndexSelectHandler(handler) {
            this.activeItemIndexSelectHandler = this.activeItemIndexSelectHandler.filter(h => h !== handler);
        },

        registerKeyupEnterHandler(handler) {
            this.keyupEnterHandler.push(handler);
        },

        unregisterKeyupEnterHandler(handler) {
            this.keyupEnterHandler = this.keyupEnterHandler.filter(h => h !== handler);
        },

        getLabelSearchType(type) {
            if (!type && !this.currentSearchType) {
                type = 'all';
            }

            if (!type && this.currentSearchType) {
                type = this.currentSearchType;
            }

            if (type.startsWith('custom_entity_') || type.startsWith('ce_')) {
                const snippetKey = `${type}.moduleTitle`;
                return this.$te(snippetKey) ? this.$tc(snippetKey) : type;
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
                this.showModuleFiltersContainer = false;
            }
        },

        clearSearchTerm() {
            this.showResultsContainer = false;
            this.showResultsSearchTrends = false;
            this.activeResultPosition = 0;
        },

        onFocusInput() {
            this.isActive = true;

            if (this.searchTerm === '#') {
                this.showTypeContainer();
            }

            if (this.resultsSearchTrends?.length) {
                this.showModuleFiltersContainer = false;
                this.showResultsSearchTrends = true;
                return;
            }

            this.loadSearchTrends().then(response => {
                this.resultsSearchTrends = response;

                this.showResultsSearchTrends = true;
            });

            if (this.resultsSearchTrends?.length) {
                this.showResultsSearchTrends = true;
                return;
            }

            this.loadSearchTrends().then(response => {
                this.resultsSearchTrends = response;

                this.showResultsSearchTrends = true;
            });
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

            if (this.searchTerm.trim().length > 155) {
                return;
            }

            this.showTypeSelectContainer = false;
            this.showResultsSearchTrends = false;

            if (this.typeSearchAlwaysInContainer && this.currentSearchType && this.searchTypes[this.currentSearchType]) {
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
            this.showModuleFiltersContainer = false;
            this.showResultsContainer = false;
            this.showResultsSearchTrends = false;
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
            this.$refs.searchInput.focus();
        },

        setSearchType(type) {
            this.currentSearchType = type;
            this.showTypeSelectContainer = false;
            this.showModuleFiltersContainer = false;
            this.showResultsSearchTrends = false;
            this.searchTerm = '';
        },

        toggleOffCanvas() {
            this.isOffCanvasShown = !this.isOffCanvasShown;

            this.$root.$emit('toggle-offcanvas', this.isOffCanvasShown);
        },

        resetSearchType() {
            if (this.searchTerm.length === 0) {
                this.isComponentMounted = false;
                this.currentSearchType = null;
            }
        },

        doListSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            this.$emit('search', searchTerm);
        }, 750),

        doListSearchWithContainer: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();

            if (searchTerm && searchTerm.length > 0) {
                this.loadTypeSearchResults(searchTerm);
            } else {
                this.showResultsContainer = false;
            }
        }, Context.app.adminEsEnable ? 30 : 750),

        doGlobalSearch: utils.debounce(function debouncedSearch() {
            const searchTerm = this.searchTerm.trim();
            if (searchTerm && searchTerm.length > 0) {
                this.loadResults(searchTerm);
            } else {
                this.showResultsContainer = false;
                this.showResultsSearchTrends = false;
            }
        }, Context.app.adminEsEnable ? 30 : 750),

        async loadResults(searchTerm) {
            this.isLoading = true;
            this.results = [];

            const entities = this.getModuleEntities(searchTerm);

            // eslint-disable-next-line no-unused-expressions
            entities?.length && this.results.unshift({
                entity: 'module',
                total: entities.length,
                entities,
            });

            if (!this.userSearchPreference || Object.keys(this.userSearchPreference).length < 1) {
                this.activeResultColumn = 0;
                this.activeResultIndex = 0;
                this.isLoading = false;

                if (!this.showTypeSelectContainer) {
                    this.showResultsContainer = true;
                }

                return;
            }

            let response;
            if (this.adminEsEnable) {
                const names = [];
                Object.keys(this.userSearchPreference).forEach((key) => {
                    if (utils.types.isEmpty(this.userSearchPreference[key])) {
                        return;
                    }
                    names.push(key);
                });

                response = await this.searchService.elastic(
                    searchTerm,
                    names,
                    this.searchLimit + 1,
                    { 'sw-inheritance': true },
                );
            } else {
                // Set limit as `searchLimit + 1` to check if more than `searchLimit` results are returned
                const queries = this.searchRankingService.buildGlobalSearchQueries(
                    this.userSearchPreference,
                    searchTerm,
                    this.criteriaCollection,
                    this.searchLimit + 1,
                    0,
                );
                response = await this.searchService.searchQuery(queries, { 'sw-inheritance': true });
            }

            const data = response.data;

            if (!data) {
                return;
            }

            Object.keys(data).forEach(entity => {
                if (data[entity].total > 0) {
                    const item = data[entity];

                    item.entities = Object.values(item.data).slice(0, this.searchLimit);
                    item.entity = entity;

                    this.results = this.results.filter(result => entity !== result.entity);

                    this.results = [
                        ...this.results,
                        item,
                    ];
                }
            });

            this.activeResultColumn = 0;
            this.activeResultIndex = 0;
            this.isLoading = false;

            if (!this.showTypeSelectContainer) {
                this.showResultsContainer = true;
            }
        },

        async loadTypeSearchResults(searchTerm) {
            // If searchType has an "entityService" load by service, otherwise load by entity
            if (this.searchTypes[this.currentSearchType]?.entityService) {
                this.loadTypeSearchResultsByService(searchTerm);
                return;
            }

            this.isLoading = true;
            this.results = [];
            const entityResults = {
                entity: this.currentSearchType,
                total: 0,
            };

            const entityName = this.searchTypes[this.currentSearchType].entityName;
            if (this.adminEsEnable) {
                const response = await this.searchService.elastic(
                    searchTerm,
                    [entityName],
                    this.searchLimit + 1,
                    { 'sw-inheritance': true },
                );

                const data = response?.data[this.currentSearchType] ?? { total: 0, data: {} };

                entityResults.total = data.total;
                entityResults.entities = Object.values(data.data).slice(0, this.searchLimit);
            } else {
                const repository = this.repositoryFactory.create(entityName);

                let criteria = this.criteriaCollection.hasOwnProperty(entityName)
                    ? this.criteriaCollection[entityName]
                    : new Criteria(1, this.searchLimit + 1);

                criteria.setTerm(searchTerm);
                // Set limit as `searchLimit + 1` to check if more than `searchLimit` results are returned
                criteria.setLimit(this.searchLimit + 1);
                criteria.setTotalCountMode(0);
                const searchRankingFields = await this.searchRankingService.getSearchFieldsByEntity(entityName);
                if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
                    entityResults.total = 0;

                    this.results.push(entityResults);
                    this.isLoading = false;
                    if (!this.showTypeSelectContainer) {
                        this.showResultsContainer = true;
                    }

                    return;
                }

                criteria = this.searchRankingService.buildSearchQueriesForEntity(
                    searchRankingFields,
                    searchTerm,
                    criteria,
                );

                const response = await repository.search(criteria, { ...Shopware.Context.api, inheritance: true });

                entityResults.total = response.total;
                entityResults.entities = response.slice(0, this.searchLimit);
            }


            if (entityResults.total > 0) {
                this.results = this.results.filter(result => this.currentSearchType !== result.entity);

                this.results = [
                    ...this.results,
                    entityResults,
                ];
            }

            this.isLoading = false;

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

            return module?.manifest?.icon ?? 'regular-books';
        },

        getEntityIconColor(entityName) {
            if (this.entitySearchColor !== '') {
                return this.entitySearchColor;
            }

            const module = this.moduleFactory.getModuleByEntityName(entityName);

            if (!module) {
                return '#AEC4DA';
            }

            return module.manifest.color || '#AEC4DA';
        },

        getEntityIcon(entityName) {
            const module = this.moduleFactory.getModuleByEntityName(entityName);

            return module?.manifest?.icon ?? 'regular-books';
        },

        isResultEmpty() {
            return !this.results.some(result => result.total !== 0);
        },

        onMouseEnterSearchType(index) {
            this.activeTypeListIndex = index;
        },

        onOpenModuleFiltersDropDown() {
            this.isActive = true;
            this.showModuleFiltersContainer = true;
            this.showTypeSelectContainer = false;
            this.showResultsSearchTrends = false;
        },

        loadSalesChannelType() {
            return new Promise(resolve => {
                this.salesChannelTypeRepository
                    .search(new Criteria(1, 25))
                    .then((response) => {
                        this.salesChannelTypes = response;
                        resolve(response);
                    });
            });
        },

        getModuleEntities(searchTerm, limit = 5) {
            const minSearch = 3;
            const regex = new RegExp(`^${searchTerm.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&').toLowerCase()}(.*)`);

            if (!searchTerm || searchTerm.length < minSearch) {
                return [];
            }

            const moduleEntities = [];

            this.searchableModules.forEach((module) => {
                const matcher = typeof module.manifest.searchMatcher === 'function'
                    ? module.manifest.searchMatcher
                    : this.getDefaultMatchSearchableModules;

                const moduleType = this.$te((`${module.manifest.title}`))
                    && this.$tc(`${module.manifest.title}`, 2);

                if (!moduleType) {
                    return;
                }

                const matches = matcher(regex, moduleType, module.manifest);

                if (!matches || matches.length === 0) {
                    return;
                }

                moduleEntities.push(
                    ...matches.filter(item => !item.privilege || this.acl.can(item.privilege)),
                );
            });

            moduleEntities.push(...this.getSalesChannelTypesBySearchTerm(regex));

            return moduleEntities.slice(0, limit);
        },

        getDefaultMatchSearchableModules(regex, label, manifest) {
            const match = label.toLowerCase().match(regex);
            const matchAddNew = (`${this.$tc('global.sw-search-bar.addNew')} ${label}`).toLowerCase().match(regex);

            if ((!match && !matchAddNew) || (!manifest?.routes?.index && !manifest?.routes?.list)) {
                return false;
            }

            const route = manifest?.routes?.index || manifest?.routes?.list;

            const { name, icon, color, entity, routes } = manifest;
            const entities = [];

            if (match && routes.index) {
                entities.push({
                    name,
                    icon,
                    color,
                    label,
                    entity,
                    route: route,
                    privilege: routes.index?.meta?.privilege,
                });
            }

            if (routes.create) {
                entities.push({
                    name,
                    icon,
                    color,
                    entity,
                    route: routes.create,
                    privilege: routes.create?.meta?.privilege,
                    action: true,
                });
            }

            return entities;
        },

        getSalesChannelTypesBySearchTerm(regex) {
            return this.salesChannelTypes.reduce((salesChannelTypes, saleChannelType) => {
                if (!saleChannelType?.translated.name.toLowerCase().match(regex)) {
                    return salesChannelTypes;
                }

                return [
                    {
                        name: 'sales-channel',
                        icon: saleChannelType?.iconName ?? 'regular-server',
                        color: '#14D7A5',
                        entity: 'sales_channel',
                        label: saleChannelType?.translated.name,
                        route: { name: 'sw.sales.channel.create', params: { typeId: saleChannelType.id } },
                        action: true,
                    },
                ];
            }, []);
        },

        toggleSearchPreferencesModal() {
            this.showSearchPreferencesModal = !this.showSearchPreferencesModal;

            // Clear search term, turn off search results
            this.searchTerm = null;
            this.showResultsContainer = false;
            this.showTypeSelectContainer = false;
            this.showResultsSearchTrends = false;
        },

        loadSearchTrends() {
            return Promise.all([this.getFrequentlyUsedModules(), this.getRecentlySearch()])
                .then(response => response.filter(item => item?.total));
        },

        getFrequentlyUsedModules() {
            return this.userActivityApiService
                .getIncrement({ cluster: this.currentUser.id })
                .then(response => {
                    const entities = Object.keys(response);

                    return {
                        entity: 'frequently_used',
                        total: entities.length,
                        entities: entities?.map(item => this.getInfoModuleFrequentlyUsed(item)),
                    };
                })
                .catch(() => {});
        },

        getRecentlySearch() {
            return new Promise(resolve => {
                const items = this.recentlySearchService.get(this.currentUser.id);

                const queries = {};

                items.forEach(item => {
                    if (!this.acl.can(`${item.entity}:read`)) {
                        return;
                    }

                    if (!queries.hasOwnProperty(item.entity)) {
                        queries[item.entity] = this.criteriaCollection.hasOwnProperty(item.entity)
                            ? cloneDeep(this.criteriaCollection[item.entity])
                            : new Criteria(1, 25);
                    }

                    const ids = [item.id, ...queries[item.entity].ids];
                    queries[item.entity].setIds(ids);
                });

                if (Object.keys(queries).length === 0) {
                    resolve();
                    return;
                }

                this.searchService.searchQuery(queries, { 'sw-inheritance': true }).then((searchResult) => {
                    if (!searchResult.data) {
                        resolve();
                        return;
                    }

                    const mapResult = [];

                    items.forEach(item => {
                        const entities = searchResult.data[item.entity] ? searchResult.data[item.entity].data : {};

                        const foundEntity = entities[item.id];

                        if (foundEntity) {
                            mapResult.push({
                                item: foundEntity,
                                entity: item.entity,
                            });
                        }
                    });

                    resolve({
                        entity: 'recently_searched',
                        total: mapResult.length,
                        entities: mapResult,
                    });
                });
            });
        },

        getInfoModuleFrequentlyUsed(key) {
            const [moduleName, routeName] = key.split('@');
            const module = this.moduleFactory.getModuleByKey('name', moduleName);

            if (!module) {
                return {};
            }

            const { routes, ...manifest } = module.manifest;

            if (typeof manifest.searchMatcher === 'function') {
                // get metadata in searchMatcher
                const metadata = manifest.searchMatcher(
                    new RegExp(`^${this.$tc(manifest.title).toLowerCase()}(.*)`),
                    this.$tc(manifest.title, 2),
                    module.manifest,
                );

                return metadata.find(item => item.route.name === routeName);
            }

            const route = Object.values(routes)
                .find(item => item.name === routeName);

            return {
                ...manifest,
                route,
                privilege: route?.meta?.privilege,
                action: route.routeKey === 'create',
            };
        },
    },
});
