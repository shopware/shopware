import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-custom-field-list.html.twig';
import './sw-custom-field-list.scss';

const { Component, StateDeprecated, Mixin } = Shopware;
const types = Shopware.Utils.types;

Component.register('sw-custom-field-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('sw-inline-snippet')
    ],

    provide() {
        return {
            SwCustomFieldListIsCustomFieldNameUnique: this.isCustomFieldNameUnique
        };
    },

    props: {
        set: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            limit: 10,
            customFields: [],
            isLoading: false,
            currentCustomField: null,
            deleteButtonDisabled: true,
            disableRouteParams: true
        };
    },

    computed: {
        customFieldAssociationStore() {
            return this.set.getAssociation('customFields');
        },
        customFieldStore() {
            return StateDeprecated.getStore('custom_field');
        }
    },

    methods: {
        onSearch(value) {
            if (!this.hasExistingCustomFields()) {
                this.term = '';
                return;
            }

            this.term = value;

            this.page = 1;
            this.getList();
        },

        hasExistingCustomFields() {
            return Object.values(this.customFieldAssociationStore.store).some((item) => {
                return !item.isLocal;
            });
        },

        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            params.sortBy = 'config.customFieldPosition';

            if (params.term) {
                params.criteria = CriteriaFactory.multi(
                    'OR',
                    ...this.getLocaleCriterias(params.term),
                    CriteriaFactory.contains('name', params.term)
                );

                params.term = '';
            }

            this.customFields = [];
            return this.customFieldAssociationStore.getList(params).then((response) => {
                this.total = response.total;
                this.customFields = response.items;
                this.isLoading = false;

                this.buildGridArray();

                return this.customFields;
            });
        },

        getLocaleCriterias(term) {
            const criterias = [];
            const locales = Object.keys(this.$root.$i18n.messages);

            locales.forEach(locale => {
                criterias.push(CriteriaFactory.contains(`config.label.\"${locale}\"`, term));
            });

            return criterias;
        },

        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        newItems() {
            const items = [];
            this.customFieldAssociationStore.forEach((item) => {
                if (item.isLocal) {
                    items.push(item);
                }
            });
            return items;
        },

        onCustomFieldDelete(customField) {
            customField.delete();

            if (customField.isLocal) {
                this.customFieldAssociationStore.removeById(customField.id);

                this.customFields.forEach((item, index) => {
                    if (item.id === customField.id) {
                        this.customFields.splice(index, 1);
                    }
                });

                this.buildGridArray();
            }
        },

        onDeleteCustomFields() {
            const selection = this.$refs.grid.getSelection();

            Object.values(selection).forEach((customField) => {
                this.onCustomFieldDelete(customField);
                this.$refs.grid.selectItem(false, customField);
            });
        },

        onAddCustomField() {
            const customField = this.customFieldAssociationStore.create();
            this.customFieldAssociationStore.removeById(customField.id);
            this.onCustomFieldEdit(customField);
        },

        onCancelCustomField() {
            this.currentCustomField = null;
        },

        onSaveCustomField() {
            this.removeEmptyProperties(this.currentCustomField.config);
            if (!this.customFieldAssociationStore.hasId(this.currentCustomField.id)) {
                this.customFieldAssociationStore.add(this.currentCustomField);
                this.buildGridArray();
            }

            this.currentCustomField = null;
        },

        onCustomFieldResetDelete(customField) {
            customField.isDeleted = false;
        },

        onInlineEditCancel(customField) {
            customField.discardChanges();
        },

        onCustomFieldEdit(customField) {
            this.currentCustomField = customField;
        },

        buildGridArray() {
            this.customFields = this.customFields.filter((value) => {
                return value.isLocal === false;
            });
            this.customFields.unshift(...this.newItems());
        },

        removeEmptyProperties(config) {
            Object.keys(config).forEach((property) => {
                if (['number', 'boolean'].includes(typeof config[property])) {
                    return;
                }

                if (types.isObject(config[property]) || types.isArray(config[property])) {
                    this.removeEmptyProperties(config[property]);
                }

                if ((types.isEmpty(config[property]) || config[property] === undefined) && config[property !== null]) {
                    this.$delete(config, property);
                }
            });
        },
        isCustomFieldNameUnique(customField) {
            // Search in local customField list for name
            const isUnique = !this.customFields.some((attr) => {
                if (customField.id === attr.id) {
                    return false;
                }
                return attr.name === customField.name;
            });

            if (!isUnique) {
                return Promise.resolve(false);
            }

            // Search the server for the customField name
            const criteria = CriteriaFactory.equals('name', customField.name);
            return this.customFieldStore.getList({ criteria }).then((res) => {
                return res.total === 0;
            });
        }
    }
});
