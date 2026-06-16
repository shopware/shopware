/*
 * @sw-package inventory
 */

import template from './sw-manufacturer-list.html.twig';
import './sw-manufacturer-list.scss';

const { Mixin } = Shopware;
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
                },
                {
                    property: 'link',
                    label: this.$t('sw-manufacturer.list.columnLink'),
                    renderer: 'text',
                    sortField: 'link',
                },
            ];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria(1, 25);

            manufacturerCriteria.addAssociation('media');

            return manufacturerCriteria;
        },
    },

    methods: {
        onChangeLanguage() {
            this.getList();
        },

        onSearch(term) {
            this.term = term ?? '';
            this.page = 1;
            this.isLoading = true;

            if (!this.$refs.manufacturerTable) {
                return Promise.resolve();
            }

            return this.$refs.manufacturerTable.setSearchTerm(this.term);
        },

        getList() {
            if (!this.$refs.manufacturerTable) {
                return Promise.resolve();
            }

            this.isLoading = true;

            return this.$refs.manufacturerTable.reload();
        },

        onMeteorTableLoadSuccess({ total }) {
            this.total = total;
            this.isLoading = false;
            this.entitySearchable = true;
        },

        onMeteorTableLoadError() {
            this.total = 0;
            this.isLoading = false;
        },

        onMeteorTableStateChange(state) {
            this.page = state.page;
            this.limit = state.limit;
            this.term = state.searchTerm;

            if (!state.sort) {
                return;
            }

            this.sortBy = state.sort.property;
            this.sortDirection = state.sort.direction;
        },
    },
};
