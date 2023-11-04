/**
 * @package system-settings
 */
import template from './sw-settings-search-searchable-content-customfields.html.twig';

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
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
    ],

    props: {
        isEmpty: {
            type: Boolean,
            required: true,
        },

        columns: {
            type: Array,
            required: true,
        },

        repository: {
            type: Object,
            required: true,
        },

        searchConfigs: {
            type: Array,
            required: false,
            default() {
                return null;
            },
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            customFields: [],
            currentCustomFieldId: null,
            addedCustomFieldIds: [],
        };
    },

    computed: {
        customFieldRepository() {
            return this.repositoryFactory.create('custom_field');
        },

        customFieldFilteredCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customFieldSet');

            if (!this.searchConfigs) {
                return criteria;
            }

            this.searchConfigs.forEach(item => {
                if (item?.customFieldId) {
                    this.addedCustomFieldIds.push(item.customFieldId);
                }
            });

            if (this.addedCustomFieldIds.length === 0) {
                return criteria;
            }

            criteria.addFilter(Criteria.not(
                'AND',
                [
                    Criteria.equalsAny('id', this.addedCustomFieldIds),
                ],
            ));

            return criteria;
        },

        customFieldCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('customFieldSet');

            return criteria;
        },
    },

    watch: {
        searchConfigs(newData) {
            if (newData[0] && newData[0]._isNew) {
                this.$refs.customGrid.enableInlineEdit();
                this.$refs.customGrid.onDbClickCell(this.$refs.customGrid.records[0]);
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.customFieldRepository.search(this.customFieldCriteria)
                .then(items => {
                    this.customFields = items;
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.loadError'),
                    });
                });
        },

        showCustomFieldWithSet(field) {
            let setName = '';
            if (field?.customFieldSet) {
                setName = this.getInlineSnippet(field.customFieldSet.config.label) || field.customFieldSet.name;
            }

            const itemName = this.getInlineSnippet(field.config.label) || field.name;
            return `${setName} - ${itemName}`;
        },

        getMatchingCustomFields(field) {
            if (!field) { return ''; }

            const fieldName = field.replace('customFields.', '');
            const fieldItem = this.customFields.find(item => item.name === fieldName);

            if (fieldItem) {
                return this.showCustomFieldWithSet(fieldItem);
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
                        message: this.$tc('sw-settings-search.notification.saveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-search.notification.saveError'),
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
                    message: this.$tc('sw-settings-search.notification.saveError'),
                });

                this.$emit('data-load');
                return;
            }

            const currentItem = this.searchConfigs.find((item) => item.field === currentField.field);
            if (!currentItem) {
                this.createNotificationError({
                    message: this.$tc('sw-settings-search.notification.saveError'),
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
        },
    },
};
