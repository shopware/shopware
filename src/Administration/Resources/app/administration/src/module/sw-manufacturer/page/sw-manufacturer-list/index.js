/*
 * @sw-package inventory
 */

import template from './sw-manufacturer-list.html.twig';
import './sw-manufacturer-list.scss';
import SwMeteorEntityDataTable from 'src/app/component/entity/sw-meteor-entity-data-table/sw-meteor-entity-data-table.vue'; // eslint-disable-line import/extensions

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    components: {
        SwMeteorEntityDataTable,
    },

    inject: [
        'repositoryFactory',
        'acl',
        'searchRankingService',
    ],

    data() {
        return {
            manufacturers: null,
            isLoading: true,
            // TODO: page is not needed anymore for "sw-meteor-entity-data-table" because it handles the page itself. The route query params should be also updated by the component itself.
            page: 1,
            // TODO: limit is not needed anymore for "sw-meteor-entity-data-table" because it handles the limit itself. The route query params should be also updated by the component itself.
            limit: 25,
            // TODO: sortBy is not needed anymore for "sw-meteor-entity-data-table" because it handles the sortBy itself. The route query params should be also updated by the component itself.
            sortBy: 'name',
            // TODO: sortDirection is not needed anymore for "sw-meteor-entity-data-table" because it handles the sortDirection itself. The route query params should be also updated by the component itself.
            sortDirection: 'ASC',
            // TODO: naturalSorting is not needed anymore for "sw-meteor-entity-data-table" because it handles the naturalSorting itself. The route query params should be also updated by the component itself.
            naturalSorting: false,
            term: undefined,
            // TODO: total is not needed anymore for "sw-meteor-entity-data-table" because it handles the total itself. The route query params should be also updated by the component itself.
            total: 0,
            searchConfigEntity: 'product_manufacturer',
            entitySearchable: true,
        };
    },

    created() {
        this.hydrateRouteState();
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        // TODO: not needed anymore for "sw-meteor-entity-data-table" because it handles the repository on his own. This would save code for repository creation in all modules.
        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        manufacturerColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-manufacturer.list.columnName',
                    primary: true,
                    clickable: true,
                    renderer: 'text',
                    previewImage: 'media.url',
                },
                {
                    property: 'link',
                    label: 'sw-manufacturer.list.columnLink',
                    inlineEdit: 'string',
                },
            ];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria(this.page, this.limit);

            manufacturerCriteria.setTerm(this.term);

            // TODO: sortBy, sortDirection and naturalSorting is not needed anymore for "sw-meteor-entity-data-table" because it handles the sorting itself. The route query params should be also updated by the component itself.
            if (this.sortBy) {
                manufacturerCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            }

            manufacturerCriteria.addAssociation('media');

            return manufacturerCriteria;
        },

        // TODO: not needed anymore for "sw-meteor-entity-data-table" because it handles the sorting itself. The route query params should be also updated by the component itself.
        currentSortBy() {
            return this.sortBy;
        },

        adminEsEnable() {
            if (!Shopware.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },
    },

    watch: {
        // TODO: the route handling should be moved to "sw-meteor-entity-data-table" because it handles the page, limit, sortBy, sortDirection and naturalSorting itself. The route query params should be also updated by the component itself.
        $route(newRoute, oldRoute) {
            if (oldRoute.name !== newRoute.name) {
                return;
            }

            const changed = this.hydrateRouteState(newRoute.query);

            if (changed) {
                this.reloadTable();
            }
        },
    },

    methods: {
        // TODO: the "sw-meteor-entity-data-table" should watch the language change itself and reload the table.
        onChangeLanguage() {
            this.reloadTable();
        },

        // TODO: This can also be done inside "sw-meteor-entity-data-table" because it handles the page, limit, sortBy, sortDirection and naturalSorting itself
        hydrateRouteState(query = this.$route.query) {
            const nextState = {
                page: parseInt(query.page, 10) || this.page,
                limit: parseInt(query.limit, 10) || this.limit,
                term: query.term ?? this.term,
                sortBy: query.sortBy || this.sortBy,
                sortDirection: query.sortDirection || this.sortDirection,
                naturalSorting: this.parseBooleanQueryValue(query.naturalSorting) || false,
            };

            const changed =
                nextState.page !== this.page ||
                nextState.limit !== this.limit ||
                nextState.term !== this.term ||
                nextState.sortBy !== this.sortBy ||
                nextState.sortDirection !== this.sortDirection ||
                nextState.naturalSorting !== this.naturalSorting;

            this.page = nextState.page;
            this.limit = nextState.limit;
            this.term = nextState.term;
            this.sortBy = nextState.sortBy;
            this.sortDirection = nextState.sortDirection;
            this.naturalSorting = nextState.naturalSorting;

            return changed;
        },

        // TODO: this is also not needed anymore
        parseBooleanQueryValue(value) {
            if (String(value).toLowerCase() === 'true') {
                return true;
            }

            if (String(value).toLowerCase() === 'false') {
                return false;
            }

            return value === true;
        },

        // TODO: also not needed, will be handled by "sw-meteor-entity-data-table" 
        updateRoute(customQuery) {
            const route = {
                name: this.$route.name,
                params: this.$route.params,
                query: {
                    limit: customQuery.limit ?? this.limit,
                    page: customQuery.page ?? this.page,
                    term: customQuery.term ?? this.term,
                    sortBy: customQuery.sortBy ?? this.sortBy,
                    sortDirection: customQuery.sortDirection ?? this.sortDirection,
                    naturalSorting: customQuery.naturalSorting ?? this.naturalSorting,
                },
            };

            if (Shopware.Utils.types.isEmpty(this.$route.query)) {
                return this.$router.replace(route);
            }

            return this.$router.push(route);
        },

        // TODO: also not needed anymore
        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            return this.updateRoute({
                page,
                limit,
            });
        },

        // TODO: also not needed anymore
        onSortColumn(column, sortDirection) {
            this.page = 1;
            this.sortBy = column.dataIndex || column.property;
            this.sortDirection = sortDirection;
            this.naturalSorting = column.naturalSorting || false;
            this.isLoading = true;

            return this.updateRoute({
                page: 1,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
                naturalSorting: this.naturalSorting,
            });
        },

        // TODO: also not needed anymore
        onSearch(value) {
            this.term = value;
            this.page = 1;
            this.isLoading = true;

            void this.updateRoute({
                term: this.term,
                page: 1,
            });

            this.$nextTick(() => {
                this.reloadTable();
            });
        },

        // TODO: also not needed anymore
        reloadTable() {
            this.isLoading = true;

            return this.$refs.manufacturerTable?.reload?.();
        },

        // TODO: we should have only one criteria for "sw-meteor-entity-data-table"
        async resolveManufacturerCriteria(criteria) {
            this.isLoading = true;

            if (this.adminEsEnable) {
                criteria.setTerm(this.term);
            } else {
                criteria = await this.addQueryScores(this.term, criteria);
            }

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return null;
            }

            if (this.term) {
                criteria.resetSorting();
            }

            return criteria;
        },

        // TODO: this is also not needed anymore
        onTableLoadSuccess({ records, total }) {
            this.manufacturers = records;
            this.total = total;
            this.isLoading = false;
        },

        // TODO: this is also not needed anymore
        onTableLoadError() {
            this.isLoading = false;
        },

        isValidTerm(term) {
            return this.searchRankingService.isValidTerm(term);
        },

        // TODO: I am not sure how we handle this now with the new "sw-meteor-entity-data-table" component. We should look for good solution to handle this in a generic way for all modules. 
        async addQueryScores(term, originalCriteria) {
            this.entitySearchable = true;

            if (!this.searchConfigEntity || !this.isValidTerm(term)) {
                return originalCriteria;
            }

            const searchRankingFields = await this.searchRankingService.getSearchFieldsByEntity(this.searchConfigEntity);

            if (!searchRankingFields || Object.keys(searchRankingFields).length < 1) {
                this.entitySearchable = false;

                return originalCriteria;
            }

            return this.searchRankingService.buildSearchQueriesForEntity(searchRankingFields, term, originalCriteria);
        },
    },
};
