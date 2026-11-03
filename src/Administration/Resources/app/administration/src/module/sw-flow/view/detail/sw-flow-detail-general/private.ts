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
    name: 'sw-flow-detail-general',
    load: () => import('./src/sw-flow-detail-general'),
    public: {
        props: [
            'isLoading',
            'isNewFlow',
            'isTemplate',
            'isUnknownTrigger',
        ],
        blocks: [
            'sw_flow_detail_general',
            'sw_flow_detail_general_information',
            'sw_flow_detail_general_information_name',
            'sw_flow_detail_general_information_description',
            'sw_flow_detail_general_information_priority',
            'sw_flow_detail_general_information_active',
        ],
        computed: [
            'logGridColumns',
            'isFlowTemplate',
        ],
    },
});
