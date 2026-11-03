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
    name: 'sw-notification-center-item',
    load: () => import('./src/sw-notification-center-item'),
    public: {
        props: [
            'notification',
        ],
        emits: [
            'center-close',
        ],
        blocks: [
            'sw_notification_center_item',
            'sw_notification_center_item_header',
            'sw_notification_center_item_header_title',
            'sw_notification_center_item_header_timestamp',
            'sw_notification_center_item_header_delete',
            'sw_notification_center_item_content',
            'sw_notification_center_item_content_message',
            'sw_notification_center_item_loader',
            'sw_notification_center_item_actions',
            'sw_notification_center_item_actions_inner',
            'sw_notification_center_item_actions_item_container',
            'sw_notification_center_item_actions_item',
        ],
        computed: [
            'itemHeaderClass',
            'notificationActions',
        ],
        methods: [
            'isNotificationFromSameDay',
            'onDelete',
            'handleAction',
        ],
    },
});
