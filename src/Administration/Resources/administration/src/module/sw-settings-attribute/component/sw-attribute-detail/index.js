import { Component, Mixin } from 'src/core/shopware';
import template from './sw-attribute-detail.html.twig';
import './sw-attribute-detail.scss';

Component.register('sw-attribute-detail', {
    template,

    inject: ['attributeDataProviderService', 'SwAttributeListIsAttributeNameUnique'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        currentAttribute: {
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
            return this.currentAttribute.config.attributeType;
        },
        renderComponentName() {
            return this.fieldTypes[this.currentAttribute.config.attributeType].configRenderComponent;
        },
        modalTitle() {
            if (this.currentAttribute.isLocal) {
                return this.$tc('sw-settings-attribute.attribute.detail.titleNewAttribute');
            }

            return this.$tc('sw-settings-attribute.attribute.detail.titleEditAttribute');
        },
        labelSaveButton() {
            if (this.currentAttribute.isLocal) {
                return this.$tc('sw-settings-attribute.attribute.detail.buttonSaveApply');
            }

            return this.$tc('sw-settings-attribute.attribute.detail.buttonEditApply');
        }
    },

    watch: {
        required(value) {
            if (value) {
                this.currentAttribute.config.validation = 'required';
                return;
            }

            if (this.currentAttribute.config.hasOwnProperty('validation')) {
                this.$delete(this.currentAttribute.config, 'validation');
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fieldTypes = this.attributeDataProviderService.getTypes();

            if (!this.currentAttribute.config.hasOwnProperty('attributeType')) {
                this.$set(this.currentAttribute.config, 'attributeType', '');
            }

            if (!this.currentAttribute.name) {
                this.currentAttribute.name = `${this.set.name.toLowerCase()}_`;
            }

            if (this.currentAttribute.config.hasOwnProperty('validation')) {
                this.required = (this.currentAttribute.config.validation === 'required');
            }

            if (!this.currentAttribute.config.hasOwnProperty('attributePosition')) {
                this.$set(this.currentAttribute.config, 'attributePosition', 1);
            }
        },
        onCancel() {
            if (this.currentAttribute !== null && !this.currentAttribute.isLocal) {
                this.currentAttribute.discardChanges();
            }
            this.$emit('cancel-attribute-edit', this.currentAttribute);
        },
        onSave() {
            this.applyTypeConfiguration();
            if (!this.currentAttribute.isLocal) {
                this.$emit('save-attribute-edit', this.currentAttribute);
                return;
            }

            this.SwAttributeListIsAttributeNameUnique(this.currentAttribute).then(isUnique => {
                if (isUnique) {
                    this.$emit('save-attribute-edit', this.currentAttribute);
                    return;
                }
                this.createNameNotUniqueNotification();
            });
        },
        createNameNotUniqueNotification() {
            const titleSaveSuccess = this.$tc('sw-settings-attribute.set.detail.titleNameNotUnique');
            const messageSaveSuccess = this.$tc('sw-settings-attribute.set.detail.messageNameNotUnique');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },
        applyTypeConfiguration() {
            const attributeType = this.currentAttribute.config.attributeType;

            if (!this.currentAttribute.type) {
                this.currentAttribute.type = this.fieldTypes[attributeType].type || attributeType;
            }
            this.currentAttribute.config = {
                ...this.fieldTypes[attributeType].config,
                ...this.currentAttribute.config
            };
        }
    }
});
