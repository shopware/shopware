import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-sequence',
    load: () => import('./src/sw-flow-sequence'),
    public: {
        props: [
            'sequence',
            'disabled',
            'isUnknownTrigger',
        ],
        blocks: [
            'sw_flow_sequence',
            'sw_flow_sequence_selector',
            'sw_flow_sequence_condition',
            'sw_flow_sequence_action',
            'sw_flow_sequence_extension',
            'sw_flow_sequence_true_block',
            'sw_flow_sequence_false_block',
        ],
        computed: [
            'sequenceData',
            'isSelectorSequence',
            'isConditionSequence',
            'isActionSequence',
            'trueBlockClasses',
        ],
    },
});
