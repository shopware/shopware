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
    name: 'sw-wizard',
    load: () => import('./src/sw-wizard'),
    public: {
        props: [
            'showNavigationDots',
            'activePage',
            'leftButtonDisabled',
            'rightButtonDisabled',
        ],
        emits: [
            'finish',
            'pages-updated',
            'current-page-change',
            'close',
        ],
        slots: [
            'default',
            'footer-left-button',
            'footer-dot-navigation',
            'footer-right-button',
        ],
        blocks: [
            'sw_wizard',
            'sw_wizard_slot_default',
            'sw_wizard_slot_footer',
            'sw_wizard_slot_footer_left_buttons',
            'sw_wizard_slot_footer_left_buttons_close',
            'sw_wizard_slot_footer_left_buttons_back',
            'sw_wizard_slot_footer_dot_navigation',
            'sw_wizard_slot_footer_right_button',
            'sw_wizard_slot_footer_right_button_finish',
            'sw_wizard_slot_footer_right_button_next',
        ],
        computed: [
            'hasFooterSlot',
            'pagesCount',
        ],
        methods: [
            'mountedComponent',
            'addPage',
            'removePage',
            'nextPage',
            'previousPage',
            'changePage',
            'onClose',
        ],
    },
});
