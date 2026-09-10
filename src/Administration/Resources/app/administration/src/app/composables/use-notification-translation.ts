/**
 * @sw-package framework
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { useI18n } from 'vue-i18n';
import Sanitizer from 'src/core/helper/sanitizer.helper';
import type { NotificationType } from '../store/notification.store';

/**
 * Composable alternative to the `notification-translation` mixin: the shared rendering helpers of the
 * notification components. A snippet key is translated while a plain string passes through, and the
 * message is sanitized once here so the allow-list is not repeated per component.
 *
 * The mixin read `this.$te`/`this.$t` and the global `this.$sanitize`; here they come from `useI18n()`
 * and from the Sanitizer helper that `$sanitize` is bound to. The mixin stays in place for Options API
 * components.
 *
 * Keep this and `src/app/mixin/notification-translation.mixin.ts` in sync — change both together.
 *
 * @private
 */
export default function useNotificationTranslation(): {
    getTranslatedTitle: (notification: NotificationType) => string;
    getTranslatedMessage: (notification: NotificationType) => string;
} {
    // The composer object is kept instead of destructuring `t`/`te`: they are declared as methods, so
    // destructured references trip @typescript-eslint/unbound-method.
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
