import template from './sw-settings-salutation-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-salutation-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            isLoading: false,
            limit: 10,
            salutations: null,
            sortBy: 'salutationKey',
            sortDirection: 'ASC'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTerm(this.term);

            this.salutationRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                this.total = searchResult.total;
                this.salutations = searchResult;
                this.isLoading = false;
            });
        },

        getColumns() {
            return [{
                property: 'salutationKey',
                label: 'sw-settings-salutation.list.columnSalutationKey',
                inlineEdit: 'string',
                routerLink: 'sw.settings.salutation.detail',
                primary: true
            }, {
                property: 'displayName',
                label: 'sw-settings-salutation.list.columnDisplayName',
                inlineEdit: 'string',
                primary: true
            }, {
                property: 'letterName',
                label: 'sw-settings-salutation.list.columnLetterName',
                inlineEdit: 'string'
            }];
        }
    }
});
