/**
 * @sw-package framework
 */

/**
 * @private
 */
export const STORAGE_KEYS = {
    ADMIN_LOCALE: 'sw-admin-locale',
    REDIRECT_FROM_LOGIN: 'redirectFromLogin',
    SSO_SESSION: 'sw-sso-session',
    PREVIOUS_ROUTE: 'sw-admin-previous-route',
    SHOULD_RELOAD: 'sw-login-should-reload',
} as const;

/**
 * @private
 */
export const HTTP_STATUS = {
    TOO_MANY_REQUESTS: 429,
} as const;

/**
 * @private
 */
export const TIMING = {
    COUNTDOWN_INTERVAL_MS: 1000,
    SECONDS_PER_MINUTE: 60,
} as const;

/**
 * @private
 */
export const ROUTES = {
    CORE: 'core',
    FIRST_RUN_WIZARD: 'sw.first.run.wizard.index',
    FIRST_RUN_WIZARD_PREFIX: 'sw.first.run.wizard',
} as const;
