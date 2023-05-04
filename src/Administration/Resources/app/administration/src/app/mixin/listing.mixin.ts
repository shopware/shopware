/**
 * @package admin
 */

/* @private */
import type { Dictionary } from 'vue-router/types/router';
import type { RawLocation } from 'vue-router';
import type Criteria from '@shopware-ag/admin-extension-sdk/es/data/Criteria';

/* @private */
export {};

/* Mixin uses many untyped dependencies */
/* eslint-disable @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access,max-len,@typescript-eslint/no-unsafe-return,@typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-explicit-any */

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Mixin.register('listing', {
    inject: ['searchRankingService', 'feature'],

    data(): {
        page: number,
        limit: number,
        total: number,
        sortBy: string|null,
        sortDirection: string,
        naturalSorting: boolean,
        selection: Record<string, any>,
        term: string|undefined,
        disableRouteParams: boolean,
        searchConfigEntity: string|null,
        entitySearchable: boolean,
        freshSearchTerm: boolean,
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
            active: boolean,
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
        if (this.disableRouteParams) {
            this.getList();
            return;
        }

        const actualQueryParameters: Dictionary<(string|null)[]|string|boolean> = this.$route.query;

        // When no route information are provided
        if (Shopware.Utils.types.isEmpty(actualQueryParameters)) {
            this.resetListing();
        } else {
            // When we get the parameters on the route, true and false will be a string so we should convert to boolean
            Object.keys(actualQueryParameters).forEach((key) => {
                if (actualQueryParameters[key] === 'true') {
                    actualQueryParameters[key] = true;
                } else if (actualQueryParameters[key] === 'false') {
                    actualQueryParameters[key] = false;
                }
            });

            // otherwise update local data and fetch from server
            this.updateData(actualQueryParameters);
            this.getList();
        }
    },

    watch: {
        // Watch for changes in query parameters and update listing
        '$route'(newRoute, oldRoute) {
            if (this.disableRouteParams) {
                return;
            }

            const query = this.$route.query;

            if (Shopware.Utils.types.isEmpty(query)) {
                this.resetListing();
            }

            // Update data information from the url
            this.updateData(query);

            if (newRoute.query[this.storeKey] !== oldRoute.query[this.storeKey] && this.filterCriteria.length) {
                // @ts-expect-error - filterCriteria is defined in base component
                this.filterCriteria = [];
                return;
            }

            // Fetch new list
            this.getList();
        },

        selection() {
            Shopware.State.commit('shopwareApps/setSelectedIds', Object.keys(this.selection));
        },

        term(newValue) {
            if (newValue && newValue.length) {
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
            page?: number,
            limit?: number,
            term?: string,
            sortBy?: string,
            sortDirection?: string,
            naturalSorting?: boolean,
        }) {
            this.page = parseInt(customData.page as unknown as string, 10) || this.page;
            this.limit = parseInt(customData.limit as unknown as string, 10) || this.limit;
            this.term = customData.term ?? this.term;
            this.sortBy = customData.sortBy || this.sortBy;
            this.sortDirection = customData.sortDirection || this.sortDirection;
            this.naturalSorting = customData.naturalSorting || this.naturalSorting;
        },

        updateRoute(customQuery: {
            limit?: number,
            page?: number,
            term?: string,
            sortBy?: string,
            sortDirection?: string,
            naturalSorting?: boolean,
        }, queryExtension = {}) {
            // Get actual query parameter
            const query = customQuery || this.$route.query;
            const routeQuery = this.$route.query;

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
            if (Shopware.Utils.types.isEmpty(routeQuery)) {
                void this.$router.replace(route as unknown as RawLocation);
            } else {
                void this.$router.push(route as unknown as RawLocation);
            }
        },

        resetListing() {
            this.updateRoute({
                // @ts-expect-error
                name: this.$route.name,
                query: {
                    limit: this.limit,
                    page: this.page,
                    term: this.term,
                    sortBy: this.sortBy,
                    sortDirection: this.sortDirection,
                    naturalSorting: this.naturalSorting,
                },
            });
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

        onPageChange(opts: {
            page: number,
            limit: number,
        }) {
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

        onSearch(value: string|undefined) {
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

        onSort({ sortBy, sortDirection }: {
            sortBy: string,
            sortDirection: string,
        }) {
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

        onSortColumn(column: {
            dataIndex: string,
            naturalSorting: boolean,
        }) {
            if (this.disableRouteParams) {
                if (this.sortBy === column.dataIndex) {
                    this.sortDirection = (this.sortDirection === 'ASC' ? 'DESC' : 'ASC');
                } else {
                    this.sortDirection = 'ASC';
                    this.sortBy = column.dataIndex;
                }
                this.getList();
                return;
            }

            if (this.sortBy === column.dataIndex) {
                this.updateRoute({
                    sortDirection: (this.sortDirection === 'ASC' ? 'DESC' : 'ASC'),
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

            return this.searchRankingService.buildSearchQueriesForEntity(
                searchRankingFields,
                term,
                originalCriteria,
            );
        },
    },
});
