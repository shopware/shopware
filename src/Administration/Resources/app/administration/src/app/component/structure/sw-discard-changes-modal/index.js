import template from './sw-discard-changes-modal.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @description
 * A modal that prompts the user if he wants to leave a detail page with unsaved changes.
 * @status ready
 * @example-type static
 * @see sw-category-detail
 * @component-example
 *  <sw-discard-changes-modal v-if="showDiscardChangesModal" @keep-editing="keepEditing" @discard-changes="discardChanges">
 *  </sw-discard-changes-modal>
 */
export default {
    template,

    emits: [
        'keep-editing',
        'discard-changes',
    ],

    methods: {
        keepEditing() {
            this.$emit('keep-editing');
        },

        discardChanges() {
            this.$emit('discard-changes');
        },
    },
};
