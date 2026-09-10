import template from './sw-mixin-emits-declared.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    // One of these is also a mixin event, so the merged list must not repeat it.
    emits: [
        'media-sidebar-items-delete',
        'selection-cleared',
    ],

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
            this.$emit('selection-cleared');
        },
    },
};
