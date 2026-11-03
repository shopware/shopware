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
    name: 'sw-shortcut-overview',
    load: () => import('./src/sw-shortcut-overview'),
    public: {
        props: [
            'showModal',
        ],
        emits: [
            'shortcut-open',
            'shortcut-close',
        ],
        blocks: [
            'sw_shortcut_overview',
            'sw_shortcut_overview_modal',
            'sw_shortcut_overview_modal_section_general_shortcuts',
            'sw_shortcut_overview_modal_section_adding_items',
            'sw_shortcut_overview_modal_section_navigation',
            'sw_shortcut_overview_modal_section_special_shortcuts',
            'sw_shortcut_overview_modal_section_accessibility',
            'sw_shortcut_overview_modal_footer',
            'sw_shortcut_overview_modal_footer_disable_shortcuts',
            'sw_shortcut_overview_modal_footer_close',
        ],
        computed: [
            'platform',
            'platformShortcutSuffix',
            'sections',
        ],
        methods: [
            'createdComponent',
            'onOpenShortcutOverviewModal',
            'onCloseShortcutOverviewModal',
            'onToggleShortcutsDisabled',
        ],
    },
});
