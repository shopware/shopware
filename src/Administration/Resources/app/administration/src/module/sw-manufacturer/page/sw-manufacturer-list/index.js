import template from './sw-manufacturer-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-manufacturer-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            manufacturers: null,
            isLoading: true
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
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
                primary: true
            }, {
                property: 'link',
                label: 'sw-manufacturer.list.columnLink',
                inlineEdit: 'string'
            }];
        },

        manufacturerCriteria() {
            const criteria = new Criteria();
            const params = this.getListingParams();

            // Default sorting
            params.sortBy = params.sortBy || 'name';
            params.sortDirection = params.sortDirection || 'ASC';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(params.sortBy, params.sortDirection));

            return criteria;
        }
    },

    methods: {
        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        getList() {
            this.isLoading = true;

            return this.manufacturerRepository.search(this.manufacturerCriteria, Shopware.Context.api)
                .then((searchResult) => {
                    this.manufacturers = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },

        updateTotal({ total }) {
            this.total = total;
        }
    }
});
