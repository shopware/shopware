/**
 * @sw-package framework
 */
import { useI18n } from 'vue-i18n';
import Sanitizer from 'src/core/helper/sanitizer.helper';
import type { NotificationType } from '../store/notification.store';

/**
 * Composable alternative to the `notification-translation` mixin. Translates a
 * snippet key while plain strings pass through unchanged, and sanitizes the
 * message with the shared allow-list. The mixin used `this.$te`/`this.$t` and the
 * global `this.$sanitize`; here they come from `useI18n()` and the Sanitizer helper
 * `$sanitize` is bound to.
 *
 * Keep this and `src/app/mixin/notification-translation.mixin.ts` in sync — change both together.
 *
 * @private
 */
export function useNotificationTranslation(): {
    getTranslatedTitle: (notification: NotificationType) => string;
    getTranslatedMessage: (notification: NotificationType) => string;
} {
    // Keep the composer object rather than destructuring: destructured `t`/`te`
    // trip @typescript-eslint/unbound-method since they are declared as methods.
    const i18n = useI18n();

    function getTranslatedTitle(notification: NotificationType): string {
        if (!notification.title) {
            return '';
        }

        return i18n.te(notification.title) ? i18n.t(notification.title) : notification.title;
    }

    function getTranslatedMessage(notification: NotificationType): string {
        if (!notification.message) {
            return '';
        }

        const message = i18n.te(notification.message) ? i18n.t(notification.message) : notification.message;

        return Sanitizer.sanitize(message, {
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
    }

    return { getTranslatedTitle, getTranslatedMessage };
}
