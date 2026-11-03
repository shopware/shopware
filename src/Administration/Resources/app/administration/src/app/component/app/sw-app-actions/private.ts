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
    name: 'sw-app-actions',
    load: () => import('./src/sw-app-actions'),
    public: {
        blocks: [
            'sw_app_actions',
            'sw_app_action_modal',
            'sw_app_action_modal_header',
            'sw_app_action_modal_slot_header',
            'sw_app_action_modal_close',
            'sw_app_action_modal_close_button',
            'sw_app_action_modal_close_icon',
            'sw_app_action_modal_iframe',
            'sw_app_action_modal_confirm',
            'sw_app_action_modal_confirm_footer',
            'sw_app_action_modal_confirm_checkbox',
            'sw_app_action_modal_confirm_footer_buttons',
            'sw_app_action_modal_confirm_footer_cancel',
            'sw_app_action_modal_confirm_footer_continue',
        ],
        computed: [
            'entity',
            'view',
            'areActionsAvailable',
            'params',
            'extensionSdkButtons',
        ],
        methods: [
            'runAction',
            'loadActions',
            'onCloseModal',
            'onOpenModalConfirm',
            'onCloseModalConfirm',
            'onConfirmClose',
            'onChangeCheckboxShow',
            'getUserConfig',
            '_reloadPage',
            'saveConfig',
        ],
    },
});
