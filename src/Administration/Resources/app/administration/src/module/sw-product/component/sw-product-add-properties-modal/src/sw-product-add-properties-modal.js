/*
 * @sw-package inventory
 */

import template from './sw-product-add-properties-modal.html.twig';
import './sw-product-add-properties-modal.scss';
import { getAssetFilter, repositoryFactoryService } from '../dependencies';

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService],

    emits: ['modal-cancel', 'modal-save'],

    props: {
        newProperties: {
            type: Array,
            required: true,
        },
        propertiesAvailable: {
            type: Boolean,
            required: false,
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
            return getAssetFilter();
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
