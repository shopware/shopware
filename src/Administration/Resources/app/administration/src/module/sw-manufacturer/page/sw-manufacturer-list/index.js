import template from './sw-manufacturer-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-manufacturer-list', {
    template,

    inject: ['repositoryFactory', 'acl', 'feature'],

    mixins: [
        Mixin.getByName('listing'),
    ],

    data() {
        return {
            manufacturers: null,
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
            return this.repositoryFactory.create('product_manufacturer');
        },

        manufacturerColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                allowResize: true,
                routerLink: 'sw.manufacturer.detail',
                label: 'sw-manufacturer.list.columnName',
                inlineEdit: 'string',
                primary: true,
            }, {
                property: 'link',
                label: 'sw-manufacturer.list.columnLink',
                inlineEdit: 'string',
            }];
        },

        manufacturerCriteria() {
            const manufacturerCriteria = new Criteria(this.page, this.limit);

            manufacturerCriteria.setTerm(this.term);
            manufacturerCriteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return manufacturerCriteria;
        },
    },

    methods: {
        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.manufacturerCriteria);

            if (this.feature.isActive('FEATURE_NEXT_6040') && !this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            return this.manufacturerRepository.search(criteria)
                .then(searchResult => {
                    this.manufacturers = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },

        updateTotal({ total }) {
            this.total = total;
        },
    },
});
