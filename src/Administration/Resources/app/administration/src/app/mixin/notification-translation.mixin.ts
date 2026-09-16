/**
 * @sw-package framework
 */

import { defineComponent } from 'vue';
import type { NotificationType } from '../store/notification.store';

const { Mixin } = Shopware;

/**
 * @private
 *
 * Shared rendering helpers for the notification components (growl and notification center).
 * A snippet key is translated while plain strings pass through unchanged, and the message is
 * sanitized once here so the allow-list does not need to be repeated in every component.
 *
 * Duplicated in `src/app/composables/use-notification-translation`; change both together.
 */
export default Mixin.register(
    'notification-translation',
    defineComponent({
        methods: {
            getTranslatedTitle(notification: NotificationType): string {
                if (!notification.title) {
                    return '';
                }

                return this.$te(notification.title) ? this.$t(notification.title) : notification.title;
            },

            getTranslatedMessage(notification: NotificationType): string {
                if (!notification.message) {
                    return '';
                }

                const message = this.$te(notification.message) ? this.$t(notification.message) : notification.message;

                return this.$sanitize(message, {
                    ALLOWED_TAGS: [
                        'a',
                        'b',
                        'i',
                        'u',
                        'strong',
                        'em',
                        'br',
                    ],
                    ALLOWED_ATTR: [
                        'href',
                        'target',
                    ],
                });
            },
        },
    }),
);
