/**
 * @sw-package framework
 */
import useTheme from '../composables/use-theme';

/**
 * Part of the login boot path (see `loginInitializer` in `application.ts`),
 * so the login screen follows the persisted preference without a session.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeTheme(): void {
    useTheme();
}
