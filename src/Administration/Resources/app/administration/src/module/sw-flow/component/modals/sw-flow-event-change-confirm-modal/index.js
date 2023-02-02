import template from './sw-flow-event-change-confirm-modal.html.twig';
import './sw-flow-event-change-confirm-modal.scss';

const { Component, State } = Shopware;
const { EntityCollection } = Shopware.Data;
const { mapGetters } = Component.getComponentHelper();

/**
 * @private
 * @package business-ops
 */
export default {
    template,

    computed: {
        ...mapGetters('swFlowState', ['sequences']),
    },

    methods: {
        onConfirm() {
            const sequencesCollection = new EntityCollection(
                this.sequences.source,
                this.sequences.entity,
                Shopware.Context.api,
                null,
                [],
            );

            State.commit('swFlowState/setSequences', sequencesCollection);

            this.$emit('modal-confirm');
            this.onClose();
        },

        onClose() {
            this.$emit('modal-close');
        },
    },
};
