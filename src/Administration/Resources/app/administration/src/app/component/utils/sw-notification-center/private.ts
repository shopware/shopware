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
    name: 'sw-notification-center',
    load: () => import('./src/sw-notification-center'),
    public: {
        blocks: [
            'sw_notification_center',
            'sw_notification_center_context_button_button_slot',
            'sw_notification_center_content',
            'sw_notification_center_content_header',
            'sw_notification_center_content_context_menu',
            'sw_notification_center_content_notification_container',
            'sw_notification_center_delete_modal',
            'sw_notification_center_delete_modal_confirm_delete_text',
            'sw_notification_center_delete_modal_footer',
            'sw_notification_center_delete_modal_cancel',
            'sw_notification_center_delete_modal_confirm',
        ],
        computed: [
            'notifications',
            'hasNotifications',
            'additionalContextButtonClass',
        ],
        methods: [
            'onVisibilityChange',
            'openDeleteModal',
            'onConfirmDelete',
            'onCloseDeleteModal',
            'onEmptyStateBellClick',
            'togglePanel',
            'onPanelClose',
            'changeVisibility',
            'createNotificationFromSystemError',
        ],
    },
});
