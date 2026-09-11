/**
 * @sw-package framework
 */

// @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal

import { mount, type VueWrapper } from '@vue/test-utils';
import 'src/app/component/wizard/sw-wizard-dot-navigation';
import useTheme from 'src/app/composables/use-theme';
import swUiShellUpdate2026Modal from '../index';

/**
 * Before NEW_NAVIGATION_RELEASE_DATE, so it marks a shop or user that ran the old navigation.
 *
 * @private
 */
export const BEFORE_RELEASE = '2024-01-01T00:00:00.000Z';

/**
 * @private
 */
export const AFTER_RELEASE = '2026-12-01T00:00:00.000Z';

/**
 * @private
 */
export function setShopContext({ firstRunWizard = false, firstMigrationDate = BEFORE_RELEASE as string | null } = {}) {
    Shopware.Store.get('context').app.firstRunWizard = firstRunWizard;
    Shopware.Store.get('context').app.config.settings = {
        appUrlReachable: true,
        appsRequireAppUrl: false,
        disableExtensionManagement: false,
        firstMigrationDate,
        minSearchTermLength: 2,
    };
}

/**
 * Fakes only the clock the release-date guard reads; timers stay real for the modal's timeouts.
 *
 * @private
 */
export function setToday(date: string) {
    jest.useFakeTimers({
        now: new Date(date),
        doNotFake: [
            'setTimeout',
            'clearTimeout',
            'setInterval',
            'clearInterval',
            'setImmediate',
            'clearImmediate',
            'nextTick',
            'queueMicrotask',
            'requestAnimationFrame',
            'cancelAnimationFrame',
            'requestIdleCallback',
            'cancelIdleCallback',
            'performance',
            'hrtime',
        ],
    });
}

/**
 * @private
 */
export function setCurrentUser(createdAt: string | null = BEFORE_RELEASE) {
    Shopware.Store.get('session').setCurrentUser({
        id: 'user-id',
        createdAt,
    } as unknown as Parameters<ReturnType<typeof Shopware.Store.get<'session'>>['setCurrentUser']>[0]);
}

/**
 * Puts shop and user in the audience the modal is meant for, and resets the theme.
 *
 * @private
 */
export function setIntendedAudience() {
    setToday(AFTER_RELEASE);
    setShopContext();
    setCurrentUser();

    // Created outside any component, as theme.init does, so its lifecycle hooks stay off the modal.
    useTheme();
    useTheme().setTheme('light');
}

async function createWrapper(): Promise<VueWrapper> {
    return mount(swUiShellUpdate2026Modal, {
        global: {
            mocks: {
                $t: (snippet: string) => snippet,
            },
            stubs: {
                teleport: {
                    template: '<div><slot/></div>',
                },
                'sw-wizard-dot-navigation': await wrapTestComponent('sw-wizard-dot-navigation'),
            },
        },
    });
}

/**
 * @private
 */
export default createWrapper;
