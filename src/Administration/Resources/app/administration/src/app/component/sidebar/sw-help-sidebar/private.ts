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
    name: 'sw-help-sidebar',
    load: () => import('./src/sw-help-sidebar'),
    public: {
        props: [
            'selector',
            'focusTrigger',
        ],
        blocks: [
            'sw_help_sidebar',
            'sw_help_sidebar_support_content',
            'sw_help_sidebar_support_documentation',
            'sw_help_sidebar_support_create_support_request',
            'sw_help_sidebar_support_placeholder',
        ],
        computed: [
            'showHelpSidebar',
            'showShortcutButton',
            'shortcutTooltipKeys',
            'shortcutTooltipContent',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'updateViewportWidth',
            'beforeUnmountComponent',
            'unmountedComponent',
            'getHelpSidebarContainer',
            'setFocusToSidebar',
            'mouseDown',
            'escKey',
            'closeHelpSidebar',
            'openShortcutModal',
        ],
    },
});
