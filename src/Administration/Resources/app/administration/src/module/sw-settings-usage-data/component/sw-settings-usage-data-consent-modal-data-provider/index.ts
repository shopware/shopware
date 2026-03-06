/**
 * @sw-package framework
 */
import useConsentStore from 'src/core/consent/consent.store';
import template from './sw-settings-usage-data-consent-modal-data-provider.html.twig';

import SwSettingsUsageDataConsentModal from '../sw-settings-usage-data-consent-modal';

const ADMIN_USER_MIN_AGE_DAYS = 15;
const SHOP_MIN_AGE_DAYS = 60;
const WRONG_APP_URL_MODAL_STORAGE_KEY = 'sw-app-wrong-app-url-modal-shown';
const SHOP_ID_CHANGE_MODAL_SELECTOR = '.sw-app-shop-id-change-modal';

type ContextSettings = {
    appUrlReachable?: boolean;
    appsRequireAppUrl?: boolean;
    firstMigrationDate?: string | null;
};

function parseDate(value: unknown): Date | null {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    if (typeof value === 'string' || typeof value === 'number') {
        const parsedDate = new Date(value);

        return Number.isNaN(parsedDate.getTime()) ? null : parsedDate;
    }

    return null;
}

function isDateOlderThanDays(value: unknown, days: number): boolean {
    const date = parseDate(value);

    if (!date) {
        return false;
    }

    const threshold = new Date();
    threshold.setDate(threshold.getDate() - days);

    return date <= threshold;
}

function isFirstRunWizardActive(): boolean {
    return Shopware.Store.get('context').app.firstRunWizard === true;
}

function isWrongAppUrlModalVisible(): boolean {
    const settings = Shopware.Store.get('context').app.config.settings as ContextSettings | undefined;

    if (!settings) {
        return false;
    }

    const appUrlReachable = settings.appUrlReachable === true;
    const appsRequireAppUrl = settings.appsRequireAppUrl === true;
    const wasModalAlreadyShown = localStorage.getItem(WRONG_APP_URL_MODAL_STORAGE_KEY) !== null;

    return !appUrlReachable && appsRequireAppUrl && !wasModalAlreadyShown;
}

function isShopIdChangeModalVisible(): boolean {
    return document.querySelector(SHOP_ID_CHANGE_MODAL_SELECTOR) !== null;
}

function hasAdminUserAccountReachedMinimumAge(): boolean {
    const currentUser = Shopware.Store.get('session').currentUser as Record<string, unknown> | null;
    const createdAt = currentUser?.createdAt;

    return isDateOlderThanDays(createdAt, ADMIN_USER_MIN_AGE_DAYS);
}

function isShopOldEnough(): boolean {
    const settings = Shopware.Store.get('context').app.config.settings as ContextSettings | undefined;

    if (!settings) {
        return false;
    }

    return isDateOlderThanDays(settings.firstMigrationDate, SHOP_MIN_AGE_DAYS);
}
/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,
    name: 'sw-settings-usage-data-consent-modal-data-provider',

    components: {
        SwSettingsUsageDataConsentModal,
    },

    computed: {
        storeDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('backend_data');
            } catch {
                return false;
            }
        },
        userDataConsent() {
            const consentStore = useConsentStore();

            try {
                return consentStore.isAccepted('product_analytics');
            } catch {
                return false;
            }
        },

        areConsentsLoaded() {
            const consentStore = useConsentStore();

            return consentStore.consents.backend_data && consentStore.consents.product_analytics;
        },

        showConsentModal() {
            if (!this.areConsentsLoaded) {
                return false;
            }

            const consentStore = useConsentStore();
            if (consentStore.consents.product_analytics.status !== 'unset') {
                return false;
            }

            if (isFirstRunWizardActive() || isWrongAppUrlModalVisible() || isShopIdChangeModalVisible()) {
                return false;
            }

            return hasAdminUserAccountReachedMinimumAge() && isShopOldEnough();
        },
    },
});
