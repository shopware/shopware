/**
 * @sw-package framework
 */
import useTheme from 'src/app/composables/use-theme';
import useModuleIconColors from 'src/app/composables/use-module-icon-colors';

/**
 * Product analytics event carrying the global Admin setup of the current user.
 *
 * @private
 */
export const SESSION_SNAPSHOT_EVENT = 'admin_session_started';

/**
 * Reports the global Admin setup of the current user to product analytics. Sent once
 * per Admin boot after the user was identified. `theme` is the applied outcome, `theme_preference`
 * the stored choice, which may be `system`.
 *
 * @private
 */
export function trackSessionSnapshot(): void {
    const { theme, resolvedTheme } = useTheme();

    Shopware.Telemetry.track({
        eventName: SESSION_SNAPSHOT_EVENT,
        theme: resolvedTheme.value,
        theme_preference: theme.value,
        module_icon_colors: useModuleIconColors().enabled.value,
    });
}
