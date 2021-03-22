import template from './sw-settings-search-searchable-content-customfields.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-search-searchable-content-customfields', {
    template,

    inject: [
        'repositoryFactory',
        'acl'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    props: {
        isEmpty: {
            type: Boolean,
            required: true
        },

        columns: {
            type: Array,
            required: true
        },

        repository: {
            type: Object,
            required: true
        },

        searchConfigs: {
            type: Array,
            required: false,
            default() {
                return null;
            }
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            customFields: [],
            currentCustomFieldId: null
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldRepository.search(new Criteria(), Shopware.Context.api)
                .then(items => {
                    this.customFields = items;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.loadError')
                    });
                });
        },

        getMatchingCustomFields(field) {
            const fieldName = field.replace('customFields.', '');
            const fieldItem = this.customFields.find(item => item.name === fieldName);

            if (fieldItem && fieldItem.config.label['en-GB']) {
                return fieldItem.config.label['en-GB'];
            }

            return fieldName;
        },

        onSelectCustomField(currentField) {
            const currentCustomField = this.searchConfigs.find((configItem) => configItem._isNew);

            currentCustomField.field = `customFields.${currentField.name}`;
            currentCustomField.customFieldId = this.currentCustomFieldId;
        },

        onAddField() {
            this.$emit('config-add');
        },

        onInlineEditSave(promise) {
            promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-search.notification.saveSuccess')
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError')
                    });
                })
                .finally(() => {
                    this.currentCustomFieldId = null;
                    this.$emit('data-load');
                });
        },

        onInlineEditCancel() {
            this.currentCustomFieldId = null;
            this.$emit('data-load');
        },

        onResetRanking(currentField) {
            if (!currentField.field) {
                this.createNotificationError({
                    message: this.$tc('sw-settings-search.notification.saveError')
                });

                this.$emit('data-load');
                return;
            }

            const currentItem = this.searchConfigs.find((item) => item.field === currentField.field);
            if (!currentItem) {
                this.createNotificationError({
                    message: this.$tc('sw-settings-search.notification.saveError')
                });

                return;
            }

            currentItem.ranking = 0;
            this.$emit('config-save');
        },

        onRemove(currentField) {
            if (!currentField.field) {
                this.$emit('data-load');
                return;
            }

            this.$emit('config-delete', currentField.id);
        }
    }
});
