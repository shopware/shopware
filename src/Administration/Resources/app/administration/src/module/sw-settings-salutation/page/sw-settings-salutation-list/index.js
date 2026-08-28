/**
 * @sw-package checkout
 */
import template from './sw-settings-salutation-list.html.twig';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            isLoading: false,
            limit: 10,
            salutations: null,
            sortBy: 'position',
            sortDirection: 'ASC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns() {
            return this.getColumns();
        },

        salutationRepository() {
            return this.repositoryFactory.create('salutation');
        },

        tooltipAdd() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('salutation.creator'),
                showOnDisabledElements: true,
            };
        },
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
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.salutationRepository.search(criteria).then((searchResult) => {
                this.total = searchResult.total;
                this.salutations = searchResult;
                this.isLoading = false;
            });
        },

        getColumns() {
            return [
                {
                    property: 'position',
                    label: 'sw-settings-salutation.list.columnPosition',
                    inlineEdit: 'number',
                },
                {
                    property: 'salutationKey',
                    label: 'sw-settings-salutation.list.columnSalutationKey',
                    inlineEdit: 'string',
                    routerLink: 'sw.settings.salutation.detail',
                    primary: true,
                },
                {
                    property: 'displayName',
                    label: 'sw-settings-salutation.list.columnDisplayName',
                    inlineEdit: 'string',
                    primary: true,
                },
                {
                    property: 'letterName',
                    label: 'sw-settings-salutation.list.columnLetterName',
                    inlineEdit: 'string',
                },
            ];
        },
    },
};
