import type { AdminUiDefinition, CustomEntityDefinition } from 'src/app/service/custom-entity-definition.service';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';

import template from './sw-generic-custom-entity-list.html.twig';

const { Criteria } = Shopware.Data;
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
 * @package content
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'customEntityDefinitionService',
        'repositoryFactory',
        'acl',
    ],

    data() {
        return {
            customEntityInstances: null as EntityCollection<'generic_custom_entity'>|null,
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
        customEntityName(): string {
            const entityName = this.$route.params.entityName;

            const customEntityDefinition = this.customEntityDefinitionService.getDefinitionByName(entityName) ?? null;

            if (!customEntityDefinition) {
                return '';
            }

            return customEntityDefinition.entity;
        },

        customEntityDefinition(): Readonly<CustomEntityDefinition | null> {
            return this.customEntityDefinitionService.getDefinitionByName(this.customEntityName) ?? null;
        },

        customEntityRepository(): Repository<'generic_custom_entity'> | null {
            if (this.customEntityDefinition === null) {
                return null;
            }

            return this.repositoryFactory
                .create(this.customEntityDefinition.entity as 'generic_custom_entity');
        },

        adminConfig(): AdminUiDefinition | undefined {
            return this.customEntityDefinition?.flags['admin-ui'];
        },

        entityAccentColor(): string | undefined {
            return this.adminConfig?.color;
        },

        columnConfig(): EntityListingColumnConfig[] | [] {
            if (!this.customEntityDefinition) {
                return [];
            }

            const columns = this.customEntityDefinition.flags['admin-ui'].listing.columns;

            return columns.map((column) => {
                const snippetKey = `${this.customEntityName}.list.${column.ref}`;
                return {
                    label: this.$tc(snippetKey),
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

        emptyStateTitle(): string {
            const dynamicSnippetKey = `${this.customEntityName}.list.emptyState`;
            const fallbackSnippetKey = 'sw-custom-entity.general.emptyState';

            return this.$te(dynamicSnippetKey) ? this.$tc(dynamicSnippetKey) : this.$tc(fallbackSnippetKey);
        },

        emptyStateSubline(): string {
            const dynamicSnippetKey = `${this.customEntityName}.list.emptyStateSubline`;
            const fallbackSnippetKey = 'sw-custom-entity.general.emptyStateSubline';

            return this.$te(dynamicSnippetKey) ? this.$tc(dynamicSnippetKey) : this.$tc(fallbackSnippetKey);
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
        createdComponent(): void {
            if (this.adminConfig !== null) {
                this.sortBy = this.adminConfig?.listing?.columns?.[0]?.ref ?? '';
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access,@typescript-eslint/no-non-null-assertion
                this.$route.meta!.$module.icon = this.adminConfig?.icon;
            }

            this.parseRoute();
            void this.getList();
        },

        async getList(): Promise<void> {
            if (!this.customEntityRepository) {
                return;
            }

            this.isLoading = true;
            const customEntityInstances = await this.customEntityRepository.search(this.customEntityCriteria);
            this.customEntityInstances = customEntityInstances;
            this.total = customEntityInstances.total ?? 0;

            this.isLoading = false;
        },

        onChangeLanguage(languageId: string): void {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            void this.getList();
        },

        parseSortDirection(direction?: string): SortDirectionOptions {
            if (direction === 'ASC' || direction === 'DESC') {
                return direction;
            }

            return this.sortDirection;
        },

        parseRoute(): void {
            const routeData = this.$route.query as RouteParseOptions;
            this.page = routeData.page ? parseInt(routeData.page, 10) : this.page;
            this.limit = routeData.limit ? parseInt(routeData.limit, 10) : this.limit;
            this.term = routeData.term || this.term;
            this.sortBy = routeData.sortBy || this.sortBy;
            this.sortDirection = this.parseSortDirection(routeData.sortDirection);
            this.naturalSorting = routeData.naturalSorting ? routeData.naturalSorting === 'true' : this.naturalSorting;
        },

        updateRoute(updates: RouteUpdateOptions): void {
            void this.$router.replace({
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

        onSearch(term: string): void {
            this.updateRoute({ term });
        },

        onColumnSort({ dataIndex, naturalSorting }: ColumnSortEvent): void {
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

        onPageChange({ page, limit }: { page: number, limit: number }): void {
            this.updateRoute({ page, limit });
        },

        onUpdateRecords(entities: EntityCollection<'generic_custom_entity'>): void {
            this.customEntityInstances = entities;
            this.total = entities.total ?? 0;
        },
    },
});
