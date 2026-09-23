import template from './sw-mixin-emits-object.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    // The object form carries validators, so the mixin's events cannot be appended to it.
    emits: {
        'selection-cleared': null,
    },

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
