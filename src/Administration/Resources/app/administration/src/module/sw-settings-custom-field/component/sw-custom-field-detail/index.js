import template from './sw-custom-field-detail.html.twig';
import './sw-custom-field-detail.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-custom-field-detail', {
    template,

    inject: ['customFieldDataProviderService', 'SwCustomFieldListIsCustomFieldNameUnique'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        currentCustomField: {
            type: Object,
            required: true
        },

        set: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            fieldTypes: {},
            required: false
        };
    },

    computed: {
        locales() {
            if (this.set.config.translated && this.set.config.translated === true) {
                return Object.keys(this.$root.$i18n.messages);
            }

            return [this.$root.$i18n.fallbackLocale];
        },
        canSave() {
            return this.currentCustomField.config.customFieldType;
        },
        renderComponentName() {
            return this.fieldTypes[this.currentCustomField.config.customFieldType].configRenderComponent;
        },
        modalTitle() {
            if (this.currentCustomField._isNew) {
                return this.$tc('sw-settings-custom-field.customField.detail.titleNewCustomField');
            }

            return this.$tc('sw-settings-custom-field.customField.detail.titleEditCustomField');
        },
        labelSaveButton() {
            if (this.currentCustomField._isNew) {
                return this.$tc('sw-settings-custom-field.customField.detail.buttonSaveApply');
            }

            return this.$tc('sw-settings-custom-field.customField.detail.buttonEditApply');
        }
    },

    watch: {
        required(value) {
            if (value) {
                this.currentCustomField.config.validation = 'required';

                return;
            }

            if (this.currentCustomField.config.hasOwnProperty('validation')) {
                this.$delete(this.currentCustomField.config, 'validation');
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fieldTypes = this.customFieldDataProviderService.getTypes();

            if (!this.currentCustomField.config) {
                this.$set(this.currentCustomField, 'config', {});
            }

            if (!this.currentCustomField.config.hasOwnProperty('customFieldType')) {
                this.$set(this.currentCustomField.config, 'customFieldType', '');
            }

            if (!this.currentCustomField.name) {
                this.currentCustomField.name = `${this.set.name.toLowerCase()}_`;
            }

            if (this.currentCustomField.config.hasOwnProperty('validation')) {
                this.required = (this.currentCustomField.config.validation === 'required');
            }

            if (!this.currentCustomField.config.hasOwnProperty('customFieldPosition')) {
                this.$set(this.currentCustomField.config, 'customFieldPosition', 1);
            }
        },

        onCancel() {
            this.$emit('custom-field-edit-cancel', this.currentCustomField);
        },

        onSave() {
            this.applyTypeConfiguration();

            if (!this.currentCustomField._isNew) {
                this.$emit('custom-field-edit-save', this.currentCustomField);

                return;
            }

            this.SwCustomFieldListIsCustomFieldNameUnique(this.currentCustomField).then(isUnique => {
                if (isUnique) {
                    this.$emit('custom-field-edit-save', this.currentCustomField);

                    return;
                }

                this.createNameNotUniqueNotification();
            });
        },

        createNameNotUniqueNotification() {
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.messageNameNotUnique');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },

        applyTypeConfiguration() {
            const customFieldType = this.currentCustomField.config.customFieldType;

            if (!this.currentCustomField.type) {
                this.currentCustomField.type = this.fieldTypes[customFieldType].type || customFieldType;
            }

            this.currentCustomField.config = {
                ...this.fieldTypes[customFieldType].config,
                ...this.currentCustomField.config
            };
        }
    }
});
