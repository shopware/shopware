import type { CustomEntityDefinition } from 'src/app/service/custom-entity-definition.service';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';

import template from './sw-generic-custom-entity-list.html.twig';

const { Criteria } = Shopware.Data;
const { Component } = Shopware;
const types = Shopware.Utils.types;

interface EntityListingColumnConfig {
    label: string,
    property: string,
    routerLink: string,
    visible: boolean,
}

interface ColumnSortEvent {
    dataIndex: string,
    naturalSorting: boolean
}

interface RouteUpdateOptions {
    limit?: number,
    page?: number,
    term?: string,
    sortBy?: string,
    sortDirection?: string,
    naturalSorting?: boolean
}

type SortDirectionOptions = 'ASC' | 'DESC'

interface RouteParseOptions {
    limit?: string,
    page?: string,
    term?: string,
    sortBy?: string,
    sortDirection?: SortDirectionOptions,
    naturalSorting?: string
}

/**
 * @private
 */
Component.register('sw-generic-custom-entity-list', {
    template,

    inject: [
        'customEntityDefinitionService',
        'repositoryFactory',
        'acl',
    ],

    data() {
        return {
            customEntityName: '',
            entityAccentColor: '',
            customEntityDefinition: null as CustomEntityDefinition|null,
            customEntityRepository: null as Repository|null,
            customEntityInstances: null as EntityCollection | null,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            sortBy: '',
            sortDirection: 'ASC' as SortDirectionOptions,
            naturalSorting: false,
            isLoading: false,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columnConfig(): EntityListingColumnConfig[] | [] {
            if (!this.customEntityDefinition) {
                return [];
            }

            const columns = this.customEntityDefinition.flags['admin-ui'].listing.columns;

            return columns.map(column => {
                return {
                    label: this.$tc(`${this.customEntityName}.list.${column.ref}`),
                    property: column.ref,
                    routerLink: 'sw.custom.entity.detail',
                    visible: !column.hidden,
                };
            });
        },

        customEntityCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);

            criteria.addSorting({
                field: this.sortBy,
                naturalSorting: this.naturalSorting,
                order: this.sortDirection,
            });

            return criteria;
        },
    },

    watch: {
        '$route'() {
            if (types.isEmpty(this.$route.query)) {
                this.updateRoute({});
            }

            this.parseRoute();

            void this.getList();
        },
    },

    methods: {
        createdComponent() {
            const entityName = this.$route.params.entityName;

            const customEntityDefinition = this.customEntityDefinitionService.getDefinitionByName(entityName) ?? null;

            if (!customEntityDefinition) {
                return;
            }

            this.customEntityName = customEntityDefinition.entity;

            const adminConfig = customEntityDefinition.flags['admin-ui'];
            this.entityAccentColor = adminConfig.color;
            this.sortBy = adminConfig.listing.columns[0].ref;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            this.$route.meta.$module.icon = adminConfig.icon;

            this.customEntityRepository = this.repositoryFactory.create(customEntityDefinition.entity);
            this.customEntityDefinition = customEntityDefinition;

            this.parseRoute();
            void this.getList();
        },

        async getList() {
            if (!this.customEntityRepository) {
                return;
            }

            this.isLoading = true;
            const customEntityInstances = await this.customEntityRepository.search(this.customEntityCriteria);
            this.customEntityInstances = customEntityInstances;
            this.total = customEntityInstances.total ?? 0;

            this.isLoading = false;
        },

        onChangeLanguage(languageId: string) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            void this.getList();
        },

        parseSortDirection(direction?: string): SortDirectionOptions {
            if (direction === 'ASC' || direction === 'DESC') {
                return direction;
            }

            return this.sortDirection;
        },

        parseRoute() {
            const routeData = this.$route.query as RouteParseOptions;
            this.page = routeData.page ? parseInt(routeData.page, 10) : this.page;
            this.limit = routeData.limit ? parseInt(routeData.limit, 10) : this.limit;
            this.term = routeData.term || this.term;
            this.sortBy = routeData.sortBy || this.sortBy;
            this.sortDirection = this.parseSortDirection(routeData.sortDirection);
            this.naturalSorting = routeData.naturalSorting ? routeData.naturalSorting === 'true' : this.naturalSorting;
        },

        updateRoute(updates: RouteUpdateOptions) {
            this.$router.replace({
                query: {
                    limit: (updates.limit || this.limit).toString(),
                    page: (updates.page || this.page).toString(),
                    term: updates.term || this.term,
                    sortBy: updates.sortBy || this.sortBy,
                    sortDirection: updates.sortDirection || this.sortDirection,
                    naturalSorting: (updates.naturalSorting || this.naturalSorting) ? 'true' : 'false',
                },
            });
        },

        onSearch(term: string) {
            this.updateRoute({ term });
        },

        onColumnSort({ dataIndex, naturalSorting }: ColumnSortEvent) {
            if (this.sortBy === dataIndex) {
                this.updateRoute({
                    sortDirection: this.sortDirection === 'ASC' ? 'DESC' : 'ASC',
                });
            } else {
                this.updateRoute({
                    sortBy: dataIndex,
                    sortDirection: 'ASC',
                    naturalSorting: naturalSorting,
                });
            }
        },

        onPageChange({ page, limit }: { page: number, limit: number }) {
            this.updateRoute({ page, limit });
        },

        onUpdateRecords(entities: EntityCollection) {
            this.customEntityInstances = entities;
            this.total = entities.total ?? 0;
        },
    },
});
