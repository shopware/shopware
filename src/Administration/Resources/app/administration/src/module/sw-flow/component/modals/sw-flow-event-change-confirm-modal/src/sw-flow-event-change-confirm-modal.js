import template from './sw-flow-event-change-confirm-modal.html.twig';
import './sw-flow-event-change-confirm-modal.scss';
import { EntityCollection, getApiContext, mapState, useFlowStore } from '../dependencies';

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    emits: ['modal-confirm', 'modal-close'],

    computed: {
        ...mapState(() => useFlowStore(), ['sequences']),
    },

    methods: {
        onConfirm() {
            const sequencesCollection = new EntityCollection(
                this.sequences.source,
                this.sequences.entity,
                getApiContext(),
                null,
                [],
            );

            useFlowStore().setSequences(sequencesCollection);

            this.$emit('modal-confirm');
            this.onClose();
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
};
