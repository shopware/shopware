import template from './sw-bulk-edit-custom-fields.html.twig';
import './sw-bulk-edit-custom-fields.scss';

const { Component } = Shopware;

Component.extend('sw-bulk-edit-custom-fields', 'sw-custom-field-set-renderer', {
    template,

    props: {
        selectedCustomFields: {
            type: Object,
            required: true,
        },
        isChanged: {
            type: Boolean,
            required: false,
            default: false,
        },
        entity: {
            type: Object,
            required: false,
            default: () => ({ customFields: {} }),
        },
    },

    computed: {
        currentIsChanged: {
            get() {
                return this.isChanged;
            },
            set(newValue) {
                this.$emit('change', newValue);
            },
        },
    },

    methods: {
        toggleItemCheck($event, item) {
            if ($event) {
                this.$set(this.selectedCustomFields, item.name, this.getInheritedCustomField(item.name));
            } else {
                this.$delete(this.selectedCustomFields, item.name);
            }

            this.onCheckCustomFieldExits(item);
        },

        updateCustomField(item) {
            if (Object.keys(this.entity.customFields).some(key => { return key === item.name; })) {
                this.$set(this.selectedCustomFields, item.name, this.entity.customFields[item.name]);
            }

            this.onCheckCustomFieldExits(item);
        },

        onCheckCustomFieldExits(item) {
            this.currentIsChanged = Object.keys(this.selectedCustomFields).some(key => {
                return key === item.name;
            });
        },
    },
});
