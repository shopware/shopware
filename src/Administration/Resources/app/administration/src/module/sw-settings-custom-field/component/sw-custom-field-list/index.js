import template from './sw-custom-field-list.html.twig';
import './sw-custom-field-list.scss';

const { Criteria } = Shopware.Data;
const { Component, Mixin } = Shopware;
const types = Shopware.Utils.types;

Component.register('sw-custom-field-list', {
    template,

    mixins: [
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
            term: '',
            isLoading: false,
            currentCustomField: null,
            deleteButtonDisabled: true,
            disableRouteParams: true
        };
    },

    computed: {
        filteredCustomFields() {
            if (!this.set.customFields) {
                return [];
            }

            return this.set.customFields.filter((customField) => customField.name.includes(this.term));
        },

        customFieldRepository() {
            return Shopware.Service('repositoryFactory').create(
                this.set.customFields.entity,
                this.set.customFields.source
            );
        },

        globalCustomFieldRepository() {
            return Shopware.Service('repositoryFactory').create('custom_field');
        }
    },

    methods: {
        selectionChanged() {
            const selection = this.$refs.grid.getSelection();
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        onCustomFieldDelete(customField) {
            this.set.customFields.remove(customField.id);
        },

        onDeleteCustomFields() {
            const selection = this.$refs.grid.getSelection();

            Object.values(selection).forEach((customField) => {
                this.set.customFields.remove(customField.id);
                this.$refs.grid.selectItem(false, customField.id);
            });
        },

        onAddCustomField() {
            const customField = this.customFieldRepository.create(Shopware.Context.api);
            this.onCustomFieldEdit(customField);
        },

        onCancelCustomField() {
            this.customFieldRepository.discard(this.currentCustomField);
            this.currentCustomField = null;
        },

        onSaveCustomField() {
            this.removeEmptyProperties(this.currentCustomField.config);

            if (!this.set.customFields.has(this.currentCustomField.id)) {
                this.set.customFields.push(this.currentCustomField);
            }

            this.currentCustomField = null;
        },

        onInlineEditCancel(customField) {
            this.customFieldRepository.discard(customField);
        },

        onCustomFieldEdit(customField) {
            this.currentCustomField = customField;
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
            const isUnique = !this.set.customFields.some((attr) => {
                if (customField.id === attr.id) {
                    return false;
                }
                return attr.name === customField.name;
            });

            if (!isUnique) {
                return Promise.resolve(false);
            }

            // Search the server for the customField name
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('name', customField.name));
            return this.globalCustomFieldRepository.search(criteria, Shopware.Context.api).then((res) => {
                return res.length === 0;
            });
        }
    }
});
