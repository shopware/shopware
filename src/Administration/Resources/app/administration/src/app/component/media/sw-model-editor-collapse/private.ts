import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-collapse,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-model-editor-collapse',
    extends: 'sw-collapse',
    load: () => import('./src/sw-model-editor-collapse'),
    public: {
        props: [
            'title',
            'expandOnLoading',
        ],
        slots: [
            'header',
            'content',
        ],
        blocks: [
            'sw_collapse_panel_header_slot',
            'sw_model_editor_collapse_title',
            'sw_model_editor_collapse_buttons',
            'sw_collapse_panel',
            'sw_collapse_panel_header',
            'sw_collapse_panel_content',
            'sw_collapse_panel_content_slot',
        ],
        computed: [
            'expandButtonClass',
            'collapseButtonClass',
        ],
        methods: [
            'collapseItem',
        ],
    },
});
