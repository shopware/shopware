import { Component } from 'src/core/shopware';
import template from './sw-media-modal-delete.html.twig';


Component.register('sw-media-modal-delete', {
    template,
    props: {
        itemsToDelete: {
            required: false,
            type: Array,
            validator(value) {
                return (value.length !== 0);
            }
        }
    },
    computed: {
        showModal() {
            return this.itemsToDelete !== null;
        }
    },
    methods: {
        closeDeleteModal(originalDomEvent) {
            this.$emit('sw-media-modal-delete-close', { originalDomEvent });
        },
        deleteSelection(originalDomEvent) {
            this.$emit('sw-media-modal-delete-delete', { originalDomEvent, itemsToDelete: this.itemsToDelete });
        }
    }
});
