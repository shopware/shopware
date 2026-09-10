import template from './sw-mixin-emits.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('media-sidebar-modal-mixin'),
    ],

    props: {
        ids: {
            type: Array,
            required: true,
        },
    },

    methods: {
        onConfirmDelete() {
            this.deleteSelectedItems(this.ids);
        },
    },
};
