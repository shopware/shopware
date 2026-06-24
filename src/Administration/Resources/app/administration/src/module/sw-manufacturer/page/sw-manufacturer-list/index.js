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
            page: 1,
            limit: 25,
            sortBy: 'name',
            sortDirection: 'ASC',
            naturalSorting: false,
            term: undefined,
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
        manufacturerRepository() {
            return this.repositoryFactory.create('product_manufacturer');
        },

        manufacturerColumns() {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    allowResize: true,
                    routerLink: 'sw.manufacturer.detail',
                    label: 'sw-manufacturer.list.columnName',
                    inlineEdit: 'string',
                    primary: true,
                    clickable: true,
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

            if (this.sortBy) {
                manufacturerCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            }

            return manufacturerCriteria;
        },

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
        onChangeLanguage() {
            this.reloadTable();
        },

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

        parseBooleanQueryValue(value) {
            if (String(value).toLowerCase() === 'true') {
                return true;
            }

            if (String(value).toLowerCase() === 'false') {
                return false;
            }

            return value === true;
        },

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

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.isLoading = true;

            return this.updateRoute({
                page,
                limit,
            });
        },

        onSortColumn({ column, sortDirection }) {
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

        reloadTable() {
            this.isLoading = true;

            return this.$refs.manufacturerTable?.reload?.();
        },

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

        onTableLoadSuccess({ records, total }) {
            this.manufacturers = records;
            this.total = total;
            this.isLoading = false;
        },

        onTableLoadError() {
            this.isLoading = false;
        },

        isValidTerm(term) {
            return this.searchRankingService.isValidTerm(term);
        },

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
