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
    name: 'sw-flow-detail-flow',
    load: () => import('./src/sw-flow-detail-flow'),
    public: {
        props: [
            'isLoading',
            'isNewFlow',
            'isTemplate',
            'isUnknownTrigger',
        ],
        blocks: [
            'sw_flow_detail_flow',
            'sw_flow_detail_flow_content',
            'sw_flow_detail_trigger_card',
            'sw_flow_detail_flow_trigger_select',
            'sw_flow_detail_flow_single_select',
            'sw_flow_detail_flow_trigger_explains',
            'sw_flow_detail_flow_trigger_explains_title',
            'sw_flow_detail_flow_trigger_explains_help_text',
            'sw_flow_detail_flow_sequence_diagram',
            'sw_flow_detail_flow_start_line',
            'sw_flow_detail_flow_transition_group',
            'sw_flow_detail_flow_position',
            'sw_flow_sequence_icon_plus',
            'sw_flow_sequence_icon_connection',
            'sw_flow_detail_flow_sequences',
            'sw_flow_detail_flow_modal_extension',
        ],
        computed: [
            'sequenceRepository',
            'formatSequences',
            'rootSequences',
            'showActionWarning',
        ],
        methods: [
            'createdComponent',
            'getTriggerActions',
            'convertSequenceData',
            'convertToTreeData',
            'createSequence',
            'onEventChange',
            'onAddRootSequence',
            'getSequenceId',
        ],
    },
});
