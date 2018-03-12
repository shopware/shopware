import { Component } from 'src/core/shopware';
import template from './sw-notifications.html.twig';
import './sw-notifications.less';

Component.register('sw-notifications', {
    template,

    props: {
        position: {
            type: String,
            required: false,
            default: 'topRight'
        }
    },

    data() {
        return {
            notifications: this.$store.state.notification.notifications
        };
    },

    computed: {
        notificationStyle() {
            let notificationStyle;

            switch (this.position) {
            case 'bottomLeft':
                notificationStyle = '';
                break;
            case 'bottomRight':
                notificationStyle = '';
                break;
            default:
                notificationStyle = '';
            }

            return notificationStyle;
        }
    }
});
