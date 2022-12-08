/**
 * @package system-settings
 */
import template from './sw-custom-field-list.html.twig';
import './sw-custom-field-list.scss';

const { Criteria } = Shopware.Data;
const { Mixin } = Shopware;
const types = Shopware.Utils.types;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    provide() {
        return {
            SwCustomFieldListIsCustomFieldNameUnique: this.isCustomFieldNameUnique,
        };
    },

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('notification'),
    ],

    props: {
        set: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            term: '',
            isLoading: false,
            currentCustomField: null,
            deleteButtonDisabled: true,
            disableRouteParams: true,
            deleteCustomField: null,
            customFields: null,
            page: 1,
            total: 0,
            limit: 10,
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create(
                this.set.customFields.entity,
                this.set.customFields.source,
            );
        },

        globalCustomFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },
    },

    watch: {
        isLoading(value) {
            this.$emit('loading-changed', value);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        onSearchTermChange() {
            this.loadCustomFields();
        },

        createdComponent() {
            this.loadCustomFields();
        },

        loadCustomFields() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);

            criteria.addFilter(Criteria.equals('customFieldSetId', this.set.id));
            criteria.addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            return this.customFieldRepository.search(criteria).then((response) => {
                this.customFields = response;
                this.total = response.total;

                return response;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        selectionChanged(selection) {
            this.deleteButtonDisabled = Object.keys(selection).length <= 0;
        },

        onCustomFieldDelete(customField) {
            this.deleteCustomField = customField;
        },

        onDeleteCustomFields() {
            this.deleteCustomField = Array.from(Object.values(this.$refs.grid.getSelection()));
        },

        onAddCustomField() {
            const customField = this.customFieldRepository.create();
            this.onCustomFieldEdit(customField);
        },

        onCancelCustomField() {
            this.customFieldRepository.discard(this.currentCustomField);
            this.currentCustomField = null;
        },

        onInlineEditFinish(item) {
            this.onSaveCustomField(item);
        },

        onSaveCustomField(field = this.currentCustomField) {
            this.removeEmptyProperties(field.config);

            return this.customFieldRepository.save(field).finally(() => {
                this.currentCustomField = null;

                // Wait for modal to be closed
                this.$nextTick(() => {
                    this.loadCustomFields();
                });
            });
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
            // Search the server for the customField name
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('name', customField.name));
            return this.globalCustomFieldRepository.search(criteria).then((res) => {
                return res.length === 0;
            });
        },

        onPageChange(event) {
            this.page = event.page;

            this.loadCustomFields();
        },

        onCancelDeleteCustomField() {
            this.deleteCustomField = null;
        },

        onDeleteCustomField() {
            // contains an array with custom field id's
            const toBeDeletedCustomFields = [];
            const isArray = Array.isArray(this.deleteCustomField);

            if (isArray) {
                this.deleteCustomField.forEach(customField => toBeDeletedCustomFields.push(customField.id));
            } else {
                toBeDeletedCustomFields.push(this.deleteCustomField.id);
            }

            return this.globalCustomFieldRepository.syncDeleted(toBeDeletedCustomFields, Shopware.Context.api).then(() => {
                this.deleteButtonDisabled = true;
                this.deleteCustomField = null;

                // Wait for modal to be closed
                this.$nextTick(() => {
                    this.loadCustomFields();
                });
            });
        },
    },
};
