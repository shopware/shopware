import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-help-center-v2',
    load: () => import('./src/sw-help-center-v2'),
    public: {
        blocks: [
            'sw_help_center',
            'sw_help_sidebar',
            'sw_help_sidebar_support_content',
            'sw_help_sidebar_support_documentation',
            'sw_help_sidebar_support_create_support_request',
            'sw_help_sidebar_support_placeholder',
        ],
        computed: [
            'showHelpSidebar',
            'showShortcutModal',
        ],
        methods: [
            'onVisibilityChange',
            'openShortcutModal',
            'closeShortcutModal',
        ],
    },
});
