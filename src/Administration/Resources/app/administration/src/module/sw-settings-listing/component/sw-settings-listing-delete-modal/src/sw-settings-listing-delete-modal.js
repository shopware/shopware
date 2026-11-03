import template from './sw-settings-listing-delete-modal.html.twig';
import './sw-settings-listing-delete-modal.scss';

/**
 * @sw-package inventory
 *
 * @public
 */
export default {
    template,

    emits: ['cancel', 'delete'],

    props: {
        title: {
            type: String,
            required: true,
        },

        description: {
            type: String,
            required: true,
        },
    },

    methods: {
        emitCancel() {
            this.$emit('cancel');
        },

        emitDelete() {
            this.$emit('delete');
        },
    },
};
