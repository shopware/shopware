/*
 * @sw-package inventory
 */

import template from './sw-manufacturer-list.html.twig';
import './sw-manufacturer-list.scss';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

const EMPTY_PREVIEW_IMAGE = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            isLoading: true,
            sortBy: 'name',
            sortDirection: 'ASC',
            total: 0,
            searchConfigEntity: 'product_manufacturer',
            skipNextMeteorTableReload: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        manufacturerRepository() {
            const manufacturerRepository = this.repositoryFactory.create('product_manufacturer');
            const decoratedRepository = Object.create(manufacturerRepository);

            decoratedRepository.search = async (criteria, context) => {
                const searchResult = await manufacturerRepository.search(criteria, context);

                searchResult.forEach((manufacturer) => {
                    manufacturer.previewMediaUrl = manufacturer.media?.url || EMPTY_PREVIEW_IMAGE;
                });

                return searchResult;
            };

            return decoratedRepository;
        },

        manufacturerColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-manufacturer.list.columnName'),
                    renderer: 'text',
                    clickable: true,
                    previewImage: 'previewMediaUrl',
                    sortField: 'name',
                    sortable: true,
                    inlineEdit: 'string',
                },
                {
                    property: 'link',
                    label: this.$t('sw-manufacturer.list.columnLink'),
                    renderer: 'text',
                    sortField: 'link',
                    sortable: true,
                    inlineEdit: 'string',
                },
            ];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria(1, 25);

            manufacturerCriteria.addAssociation('media');

            return manufacturerCriteria;
        },

        adminEsEnable() {
            if (!Shopware.Feature.isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                return false;
            }

            return Context.app.adminEsEnable ?? false;
        },
    },

    watch: {
        page: 'markNextMeteorTableReloadAsHandledByPropSync',
        limit: 'markNextMeteorTableReloadAsHandledByPropSync',
        term: 'markNextMeteorTableReloadAsHandledByPropSync',
        sortBy: 'markNextMeteorTableReloadAsHandledByPropSync',
        sortDirection: 'markNextMeteorTableReloadAsHandledByPropSync',
    },

    methods: {
        onChangeLanguage() {
            this.getList();
        },

        async onSearch(term) {
            this.term = term ?? '';
            this.page = 1;
            this.isLoading = true;
            this.entitySearchable = true;

            if (!this.disableRouteParams) {
                this.updateRoute({
                    term: this.term,
                    page: 1,
                });

                return;
            }

            if (this.$refs.manufacturerTable) {
                return this.$refs.manufacturerTable.setSearchTerm(this.term);
            }

            await this.$nextTick();

            if (!this.$refs.manufacturerTable) {
                this.isLoading = false;
            }
        },

        async getList() {
            this.isLoading = true;

            if (this.$refs.manufacturerTable) {
                let skipTableReload = this.skipNextMeteorTableReload || this.meteorTableStateDiffersFromListingState();

                await this.$nextTick();

                skipTableReload =
                    skipTableReload || this.skipNextMeteorTableReload || this.meteorTableStateDiffersFromListingState();
                this.skipNextMeteorTableReload = false;

                if (skipTableReload) {
                    return;
                }

                this.entitySearchable = true;

                return this.$refs.manufacturerTable.reload();
            }

            this.entitySearchable = true;

            await this.$nextTick();

            if (!this.$refs.manufacturerTable) {
                this.isLoading = false;
            }
        },

        async resolveManufacturerCriteria({ criteria }) {
            let resolvedCriteria = criteria;

            if (this.adminEsEnable) {
                resolvedCriteria.setTerm(this.term);
            } else {
                resolvedCriteria = await this.addQueryScores(this.term, resolvedCriteria);
            }

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return null;
            }

            if (this.freshSearchTerm) {
                resolvedCriteria.resetSorting();
            }

            return resolvedCriteria;
        },

        onMeteorTableLoadSuccess({ total }) {
            this.total = total;
            this.isLoading = false;

            if (this.entitySearchable !== false) {
                this.entitySearchable = true;
            }
        },

        onMeteorTableLoadError() {
            this.total = 0;
            this.isLoading = false;
        },

        onMeteorTableStateChange(state) {
            this.page = state.page;
            this.limit = state.limit;
            this.term = state.searchTerm;

            if (state.sort) {
                this.sortBy = state.sort.property;
                this.sortDirection = state.sort.direction;
            }

            if (this.disableRouteParams) {
                return;
            }

            this.skipNextMeteorTableReload = true;
            this.updateRoute({
                page: this.page,
                limit: this.limit,
                term: this.term,
                sortBy: this.sortBy,
                sortDirection: this.sortDirection,
            });
        },

        meteorTableStateDiffersFromListingState() {
            const tableState = this.$refs.manufacturerTable?.state;

            if (!tableState) {
                return false;
            }

            return (
                tableState.page !== this.page ||
                tableState.limit !== this.limit ||
                (tableState.searchTerm ?? '') !== (this.term ?? '') ||
                tableState.sort?.property !== this.sortBy ||
                tableState.sort?.direction !== this.sortDirection
            );
        },

        markNextMeteorTableReloadAsHandledByPropSync() {
            if (!this.$refs.manufacturerTable) {
                return;
            }

            this.skipNextMeteorTableReload = true;
        },
    },
};
