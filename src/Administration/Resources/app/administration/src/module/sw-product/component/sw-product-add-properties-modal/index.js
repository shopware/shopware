/*
 * @package inventory
 */

import template from './sw-product-add-properties-modal.html.twig';
import './sw-product-add-properties-modal.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: ['modal-cancel', 'modal-save'],

    props: {
        newProperties: {
            type: Array,
            required: true,
        },
        propertiesAvailable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            properties: [],
        };
    },

    computed: {
        showSaveButton() {
            return this.propertiesAvailable;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    methods: {

        onCancel() {
            this.$emit('modal-cancel');
        },

        onSave() {
            this.$emit('modal-save', this.newProperties);
        },

        onOpenProperties() {
            this.$emit('modal-cancel');

            this.$nextTick(() => {
                this.$router.push({ name: 'sw.property.index' });
            });
        },

        onSelectOption(selection) {
            const item = selection.item;

            if (selection.selected === true) {
                this.newProperties.add(item);
            } else {
                this.newProperties.remove(item.id);
            }
        },
    },
};
