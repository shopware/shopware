import { State } from 'src/core/shopware';
import template from './sw-notifications.html.twig';
import './sw-notifications.scss';

/**
 * @private
 * @description
 * Wrapper element for all notifications of the administration.
 * @status ready
 * @example-type code-only
 */
export default {
    name: 'sw-notifications',
    template,

    props: {
        position: {
            type: String,
            required: false,
            default: 'topRight',
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['topRight', 'bottomRight'].includes(value);
            }
        },
        notificationsGap: {
            type: String,
            default: '20px'
        },
        notificationsTopGap: {
            type: String,
            default: '165px'
        }
    },

    data() {
        return {
            notifications: State.getStore('notification').notifications
        };
    },

    computed: {
        notificationsStyle() {
            let notificationsGap = this.notificationsGap;

            if (`${parseInt(notificationsGap, 10)}` === notificationsGap) {
                notificationsGap = `${notificationsGap}px`;
            }

            if (this.position === 'bottomRight') {
                return {
                    top: 'auto',
                    right: notificationsGap,
                    bottom: notificationsGap,
                    left: 'auto'
                };
            }

            return {
                top: this.notificationsTopGap,
                right: notificationsGap,
                bottom: 'auto',
                left: 'auto'
            };
        }
    },

    methods: {
        onClose(notification) {
            State.getStore('notification').removeNotification(notification);
        }
    }
};
