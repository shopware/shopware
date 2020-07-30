import template from './sw-settings-custom-field-set-list.html.twig';
import './sw-settings-custom-field-set-list.scss';

const { Component, Locale, Mixin, Data: { Criteria } } = Shopware;

Component.register('sw-settings-custom-field-set-list', {
    template,
    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'custom_field_set',
            sortBy: 'config.name',
            datetime: '',
            showModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        // Settings Listing mixin override
        titleSaveSuccess() {
            return this.$tc('global.default.success');
        },

        // Settings Listing mixin override
        messageSaveSuccess() {
            if (this.deleteEntity) {
                return this.$tc(
                    'sw-settings-custom-field.set.list.messageDeleteSuccess',
                    0,
                    { name: this.getInlineSnippet(this.deleteEntity.config.label) || this.deleteEntity.name }
                );
            }
            return '';
        },

        listingCriteria() {
            const criteria = new Criteria();

            const params = this.getListingParams();

            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    ...this.getLocaleCriterias(params.term),
                    ...this.getTermCriteria(params.term)
                ]
            ));

            return criteria;
        }
    },

    methods: {
        getLocaleCriterias(term) {
            if (!term) {
                return [];
            }

            const criterias = [];
            const locales = Locale.getLocaleRegistry();

            locales.forEach((value, key) => {
                criterias.push(Criteria.contains(
                    `config.label.\"${key}\"`, term
                ));
            });

            return criterias;
        },

        getTermCriteria(term) {
            const criterias = [];

            if (term) {
                criterias.push(Criteria.contains('name', term));
            }

            return criterias;
        }
    }
});
