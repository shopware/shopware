import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-custom-field-set-list.html.twig';
import './sw-settings-custom-field-set-list.scss';

const { Component, Mixin } = Shopware;

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
            return this.$tc('sw-settings-custom-field.set.list.titleDeleteSuccess');
        },
        // Settings Listing mixin override
        messageSaveSuccess() {
            if (this.deleteEntity) {
                return this.$tc(
                    'sw-settings-custom-field.set.list.messageDeleteSuccess',
                    0,
                    { name: this.getInlineSnippet(this.deleteEntity.config.label) }
                );
            }
            return '';
        }
    },

    methods: {
        // Settings Listing mixin override
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            if (params.term) {
                params.criteria = CriteriaFactory.multi(
                    'OR',
                    ...this.getLocaleCriterias(params.term),
                    CriteriaFactory.contains('name', params.term)
                );

                params.term = '';
            }
            this.items = [];

            return this.store.getList(params).then((response) => {
                this.total = response.total;
                this.items = response.items;
                this.isLoading = false;

                return this.items;
            });
        },
        getLocaleCriterias(term) {
            const criterias = [];
            const locales = Object.keys(this.$root.$i18n.messages);

            locales.forEach(locale => {
                criterias.push(CriteriaFactory.contains(`config.label.\"${locale}\"`, term));
            });

            return criterias;
        }
    }
});
