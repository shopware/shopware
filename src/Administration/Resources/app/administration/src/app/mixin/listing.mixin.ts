/**
 * @sw-package framework
 */

/* @private */
import type Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
import { defineComponent } from 'vue';
import type { LocationQuery, RouteLocationNamedRaw } from 'vue-router';

/* @private */
export {};

/* Mixin uses many untyped dependencies */
/* eslint-disable @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,max-len,@typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-explicit-any */

/**
 * @private
 */
export default Shopware.Mixin.register(
    'listing',
    defineComponent({
        inject: [
            'searchRankingService',
            'feature',
        ],

        data(): {
            page: number;
            limit: number;
            total: number;
            sortBy: string | null;
            sortDirection: string;
            naturalSorting: boolean;
            selection: Record<string, any>;
            term: string | undefined;
            disableRouteParams: boolean;
            searchConfigEntity: string | null;
            entitySearchable: boolean;
            freshSearchTerm: boolean;
            previousRouteName: string;
            isNavigationInProgress: boolean;
        } {
            return {
                page: 1,
                limit: 25,
                total: 0,
                sortBy: null,
                sortDirection: 'ASC',
                naturalSorting: false,
                selection: [],
                term: undefined,
                disableRouteParams: false,
                searchConfigEntity: null,
                entitySearchable: true,
                freshSearchTerm: false,
                previousRouteName: '',
                isNavigationInProgress: false,
            };
        },

        computed: {
            maxPage() {
                return Math.ceil(this.total / this.limit);
            },

            routeName() {
                return this.$route.name;
            },

            selectionArray() {
                return Object.values(this.selection);
            },

            selectionCount() {
                return this.selectionArray.length;
            },

            filters(): {
                active: boolean;
            }[] {
                // You can create your custom filters by defining the computed property "filters"
                return [];
            },

            searchRankingFields() {
                if (!this.searchConfigEntity) {
                    return {};
                }

                return this.searchRankingService.getSearchFieldsByEntity(this.searchConfigEntity);
            },

            currentSortBy() {
                return this.freshSearchTerm ? null : this.sortBy;
            },
        },

        created() {
            this.previousRouteName = this.$route.name as string;

            if (this.disableRouteParams) {
                this.getList();
                return;
            }

            const actualQueryParameters: LocationQuery = this.$route.query;

            // When no route information are provided
            if (Shopware.Utils.types.isEmpty(actualQueryParameters)) {
                this.getList();
            } else {
                this.parseBooleanQueryParams(actualQueryParameters);

                // otherwise update local data and fetch from server
                this.updateData(actualQueryParameters);
                this.getList();
            }

            // Instead of using a watch, use the router's beforeEach hook
            // This will run for all route changes but we can filter out the ones we care about
            this.$router.beforeEach((to, from, next) => {
                // Prevent recursive calls - if we're already processing a navigation, just proceed
                if (this.isNavigationInProgress) {
                    next();
                    return;
                }

                // Only handle if we're staying on the same route name
                if (to.name === from.name && to.name === this.previousRouteName && !this.disableRouteParams) {
                    if (Shopware.Utils.types.isEmpty(to.query)) {
                        window.history.back(); // this fixes an issue where you have to go back twice to get to the correct page
                        return;
                    }
                    // Update component data from the new route
                    if (!Shopware.Utils.types.isEmpty(to.query)) {
                        this.isNavigationInProgress = true;
                        this.parseBooleanQueryParams(to.query);
                        this.updateData(to.query);
                        // Load the data to populate the listing
                        this.getList();
                        // Reset the flag after a short delay to ensure the route has finished updating
                        setTimeout(() => {
                            this.isNavigationInProgress = false;
                        }, 50);
                    }
                }
                next();
            });
        },

        beforeRouteLeave() {
            Shopware.Store.get('shopwareApps').selectedIds = [];
            Shopware.Store.get('swBulkEdit').selectedIds = [];
        },

        watch: {
            selection() {
                Shopware.Store.get('shopwareApps').selectedIds = Object.keys(this.selection);
                Shopware.Store.get('swBulkEdit').selectedIds = Object.keys(this.selection);
            },

            term(newValue) {
                if (newValue?.length) {
                    this.freshSearchTerm = true;
                }
            },

            sortBy() {
                this.freshSearchTerm = false;
            },

            sortDirection() {
                this.freshSearchTerm = false;
            },
        },

        methods: {
            updateData(customData: {
                page?: number;
                limit?: number;
                term?: string;
                sortBy?: string;
                sortDirection?: string;
                naturalSorting?: boolean;
            }) {
                this.page = Number.parseInt(customData.page as unknown as string, 10) || this.page;
                this.limit = Number.parseInt(customData.limit as unknown as string, 10) || this.limit;
                this.term = customData.term ?? this.term;
                this.sortBy = customData.sortBy || this.sortBy;
                this.sortDirection = customData.sortDirection || this.sortDirection;
                this.naturalSorting = customData.naturalSorting || this.naturalSorting;
            },

            updateRoute(
                customQuery: {
                    limit?: number;
                    page?: number;
                    term?: string;
                    sortBy?: string;
                    sortDirection?: string;
                    naturalSorting?: boolean;
                },
                queryExtension = {},
            ) {
                // Get actual query parameter
                const query = customQuery || this.$route.query;

                // Create new route
                const route = {
                    name: this.$route.name,
                    params: this.$route.params,
                    query: {
                        limit: query.limit || this.limit,
                        page: query.page || this.page,
                        term: query.term || this.term,
                        sortBy: query.sortBy || this.sortBy,
                        sortDirection: query.sortDirection || this.sortDirection,
                        naturalSorting: query.naturalSorting || this.naturalSorting,
                        ...queryExtension,
                    },
                };

                // If query is empty then replace route, otherwise push
                void this.$router.replace(route as unknown as RouteLocationNamedRaw);
            },

            getMainListingParams() {
                if (this.disableRouteParams) {
                    return {
                        limit: this.limit,
                        page: this.page,
                        term: this.term,
                        sortBy: this.sortBy,
                        sortDirection: this.sortDirection,
                        naturalSorting: this.naturalSorting,
                    };
                }
                // Get actual query parameter
                const query = this.$route.query;

                return {
                    limit: query.limit,
                    page: query.page,
                    term: query.term,
                    sortBy: query.sortBy || this.sortBy,
                    sortDirection: query.sortDirection || this.sortDirection,
                    naturalSorting: query.naturalSorting || this.naturalSorting,
                };
            },

            updateSelection(selection: Record<string, any>) {
                this.selection = selection;
            },

            onPageChange(opts: { page: number; limit: number }) {
                this.page = opts.page;
                this.limit = opts.limit;
                if (this.disableRouteParams) {
                    this.getList();
                    return;
                }
                this.updateRoute({
                    page: this.page,
                });
            },

            onSearch(value: string | undefined) {
                this.term = value;

                if (this.disableRouteParams) {
                    this.page = 1;
                    this.getList();
                    return;
                }

                this.updateRoute({
                    term: this.term,
                    page: 1,
                });
            },

            onSwitchFilter(filter: any, filterIndex: number) {
                this.filters[filterIndex].active = !this.filters[filterIndex].active;

                this.page = 1;
            },

            onSort({ sortBy, sortDirection }: { sortBy: string; sortDirection: string }) {
                if (this.disableRouteParams) {
                    this.updateData({
                        sortBy,
                        sortDirection,
                    });
                } else {
                    this.updateRoute({
                        sortBy,
                        sortDirection,
                    });
                }

                this.getList();
            },

            onSortColumn(column: { dataIndex: string; naturalSorting: boolean }) {
                if (this.disableRouteParams) {
                    if (this.sortBy === column.dataIndex) {
                        this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        this.sortDirection = 'ASC';
                        this.sortBy = column.dataIndex;
                    }
                    this.getList();
                    return;
                }

                if (this.sortBy === column.dataIndex) {
                    this.updateRoute({
                        sortDirection: this.sortDirection === 'ASC' ? 'DESC' : 'ASC',
                    });
                } else {
                    this.naturalSorting = column.naturalSorting;
                    this.updateRoute({
                        sortBy: column.dataIndex,
                        sortDirection: 'ASC',
                        naturalSorting: column.naturalSorting,
                    });
                }
            },

            onRefresh() {
                this.getList();
            },

            getList() {
                Shopware.Utils.debug.warn(
                    'Listing Mixin',
                    'When using the listing mixin you have to implement your custom "getList()" method.',
                );
            },

            isValidTerm(term: string) {
                return term && term.trim().length > 1;
            },

            async addQueryScores(term: string, originalCriteria: Criteria) {
                this.entitySearchable = true;
                if (!this.searchConfigEntity || !this.isValidTerm(term)) {
                    return originalCriteria;
                }
                const searchRankingFields = await this.searchRankingService.getSearchFieldsByEntity(this.searchConfigEntity);
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
                    this.entitySearchable = false;
                    return originalCriteria;
                }

                return this.searchRankingService.buildSearchQueriesForEntity(searchRankingFields, term, originalCriteria);
            },

            /**
             * Parses all string representations of boolean values to actual boolean values.
             * Only works on root level of the query object.
             */
            parseBooleanQueryParams(query: LocationQuery) {
                Object.keys(query).map(key => {
                    if (String(query[key]).toLowerCase() === 'true') {
                        // @ts-expect-error
                        query[key] = true;
                    } else if (String(query[key]).toLowerCase() === 'false') {
                        // @ts-expect-error
                        query[key] = false;
                    }
                    return key;
                });
            },
        },
    }),
);
