import template from './sw-extension-rating-modal.html.twig';
import './sw-extension-rating-modal.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    emits: ['modal-close'],

    methods: {
        emitClose() {
            this.$emit('modal-close');
        },
    },
};
