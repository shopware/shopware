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
    name: 'sw-notifications',
    load: () => import('./src/sw-notifications'),
    public: {
        props: [
            'position',
            'notificationsGap',
            'notificationsTopGap',
        ],
        blocks: [
            'sw_notifications',
            'sw_notifications_element',
            'sw_notifications_transition_group',
            'sw_notifications_item',
            'sw_notifications_item_content',
            'sw_notifications_item_actions',
            'sw_notifications_item_action_item',
        ],
        computed: [
            'notifications',
            'notificationsStyle',
        ],
        methods: [
            'onClose',
            'handleAction',
            'getNotificationVariant',
        ],
    },
});
