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
    name: 'sw-flow-index',
    load: () => import('./src/sw-flow-index'),
    public: {
        blocks: [
            'sw_flow_index_smart_bar_actions_extension',
            'sw_flow_tabs_header_extension',
            'sw_flow_index_modal_content_custom',
        ],
        computed: [
            'searchType',
            'flowRepository',
            'flowCriteria',
            'flowTabs',
        ],
        methods: [
            'createComponent',
            'getTotal',
            'onUpdateTotalFlow',
            'onSearch',
        ],
    },
});
