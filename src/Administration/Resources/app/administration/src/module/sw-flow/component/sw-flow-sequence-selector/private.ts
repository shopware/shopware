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
    name: 'sw-flow-sequence-selector',
    load: () => import('./src/sw-flow-sequence-selector'),
    public: {
        props: [
            'sequence',
            'disabled',
        ],
        slots: [
            'title',
            'helpText',
        ],
        blocks: [
            'sw_flow_sequence_selector',
            'sw_flow_sequence_selector_title',
            'sw_flow_sequence_selector_help_text',
            'sw_flow_sequence_selector_actions',
            'sw_flow_sequence_selector_if_condition',
            'sw_flow_sequence_selector_then_action_icon_rule',
            'sw_flow_sequence_selector_then_action',
            'sw_flow_sequence_selector_then_action_icon_file',
            'sw_flow_sequence_selector_extension_options',
        ],
        computed: [
            'title',
            'helpText',
        ],
        methods: [
            'addIfCondition',
            'addThenAction',
        ],
    },
});
