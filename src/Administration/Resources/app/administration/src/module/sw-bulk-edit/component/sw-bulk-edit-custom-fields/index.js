/**
 * @package services-settings
 */
import template from './sw-bulk-edit-custom-fields.html.twig';
import './sw-bulk-edit-custom-fields.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['change'],

    props: {
        entity: {
            type: Object,
            required: false,
            default: () => ({ customFields: {} }),
        },
    },

    data() {
        return {
            selectedCustomFields: {},
        };
    },

    watch: {
        selectedCustomFields: {
            handler(value) {
                this.$emit('change', value);
            },
            deep: true,
        },
    },

    methods: {
        initializeCustomFields() {
            if (!this.entity.customFields) {
                this.entity.customFields = {};
            }

            this.$super('initializeCustomFields');
        },

        toggleItemCheck($event, item) {
            if ($event) {
                if (this.isCompatEnabled('INSTANCE_SET')) {
                    this.$set(this.selectedCustomFields, item.name, this.entity.customFields[item.name]);
                } else {
                    this.selectedCustomFields[item.name] = this.entity.customFields[item.name];
                }
            } else if (this.isCompatEnabled('INSTANCE_DELETE')) {
                this.$delete(this.selectedCustomFields, item.name);
            } else {
                delete this.selectedCustomFields[item.name];
            }
        },

        updateCustomField(item) {
            if (
                !this.entity.customFields.hasOwnProperty(item.name) ||
                !this.selectedCustomFields.hasOwnProperty(item.name)
            ) {
                return;
            }

            if (this.isCompatEnabled('INSTANCE_SET')) {
                this.$set(this.selectedCustomFields, item.name, this.entity.customFields[item.name]);
            } else {
                this.selectedCustomFields[item.name] = this.entity.customFields[item.name];
            }
        },
    },
};
