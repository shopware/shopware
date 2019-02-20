import { Component } from 'src/core/shopware';
import template from './sw-attribute-detail.html.twig';
import './sw-attribute-detail.scss';

Component.register('sw-attribute-detail', {
    template,

    inject: ['attributeDataProviderService'],

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
            this.$emit('save-attribute-edit', this.currentAttribute);
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
        },
        getRenderComponentName() {
            return this.fieldTypes[this.currentAttribute.config.attributeType].configRenderComponent;
        }
    }
});
