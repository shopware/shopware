import Plugin from 'src/plugin-system/plugin.class';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

import AddToCartEvent from 'src/plugin/google-analytics/events/add-to-cart.event';
import AddToCartByNumberEvent from 'src/plugin/google-analytics/events/add-to-cart-by-number.event';
import BeginCheckoutEvent from 'src/plugin/google-analytics/events/begin-checkout.event';
import BeginCheckoutOnCartEvent from 'src/plugin/google-analytics/events/begin-checkout-on-cart.event';
import CheckoutProgressEvent from 'src/plugin/google-analytics/events/checkout-progress.event';
import LoginEvent from 'src/plugin/google-analytics/events/login.event';
import PurchaseEvent from 'src/plugin/google-analytics/events/purchase.event';
import RemoveFromCartEvent from 'src/plugin/google-analytics/events/remove-from-cart.event';
import SearchAjaxEvent from 'src/plugin/google-analytics/events/search-ajax.event';
import SignUpEvent from 'src/plugin/google-analytics/events/sign-up.event';
import Storage from 'src/helper/storage/storage.helper';
import ViewItemEvent from 'src/plugin/google-analytics/events/view-item.event';
import ViewItemListEvent from 'src/plugin/google-analytics/events/view-item-list.event';
import ViewSearchResultsEvent from 'src/plugin/google-analytics/events/view-search-results';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

/**
 * @package buyers-experience
 */
export default class GoogleAnalyticsPlugin extends Plugin
{
    init() {
        this.cookieEnabledName = 'google-analytics-enabled';
        this.cookieAdsEnabledName = 'google-ads-enabled';
        this.storage = Storage;

        this.handleTrackingLocation();
        this.handleCookieChangeEvent();

        if (window.useDefaultCookieConsent && !CookieStorageHelper.getItem(this.cookieEnabledName)) {
            return;
        }

        this.startGoogleAnalytics();
    }

    startGoogleAnalytics() {
        const gtmScript = document.createElement('script');
        gtmScript.src = window.gtagURL;
        document.head.append(gtmScript);

        gtag('js', new Date());
        gtag('config', window.gtagTrackingId, window.gtagConfig);

        this.controllerName = window.controllerName;
        this.actionName = window.actionName;
        this.events = [];

        this.registerDefaultEvents();
        this.handleEvents();
    }

    handleTrackingLocation() {
        this.trackingUrl = new URL(window.location.href);

        const gclid = this.trackingUrl.searchParams.get('gclid');
        if (gclid) {
            this.storage.setItem(
                this._getGclidStorageKey(),
                gclid
            );
        } else if (this.storage.getItem(this._getGclidStorageKey())) {
            this.trackingUrl.searchParams.set(
                'gclid',
                this.storage.getItem(this._getGclidStorageKey())
            );
        }

        if (this.trackingUrl.searchParams.get('gclid')) {
            window.gtagConfig['page_location'] = this.trackingUrl.toString();
        }
    }

    handleEvents() {
        this.events.forEach(event => {
            if (!event.supports(this.controllerName, this.actionName)) {
                return;
            }

            event.execute();
        });
    }

    registerDefaultEvents() {
        this.registerEvent(AddToCartEvent);
        this.registerEvent(AddToCartByNumberEvent);
        this.registerEvent(BeginCheckoutEvent);
        this.registerEvent(BeginCheckoutOnCartEvent);
        this.registerEvent(CheckoutProgressEvent);
        this.registerEvent(LoginEvent);
        this.registerEvent(PurchaseEvent);
        this.registerEvent(RemoveFromCartEvent);
        this.registerEvent(SearchAjaxEvent);
        this.registerEvent(SignUpEvent);
        this.registerEvent(ViewItemEvent);
        this.registerEvent(ViewItemListEvent);
        this.registerEvent(ViewSearchResultsEvent);
    }

    /**
     * @param { AnalyticsEvent } event
     */
    registerEvent(event) {
        this.events.push(new event());
    }

    handleCookieChangeEvent() {
        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, this.handleCookies.bind(this));
    }

    handleCookies(cookieUpdateEvent) {
        const updatedCookies = cookieUpdateEvent.detail;

        this._updateConsent(updatedCookies);

        if (!Object.prototype.hasOwnProperty.call(updatedCookies, this.cookieEnabledName)) {
            return;
        }

        if (updatedCookies[this.cookieEnabledName]) {
            this.startGoogleAnalytics();
            return;
        }

        this.removeCookies();
        this.disableEvents();
    }

    removeCookies() {
        const allCookies = document.cookie.split(';');
        const gaCookieRegex = /^(_swag_ga|_gat_gtag)/;

        allCookies.forEach(cookie => {
            const cookieName = cookie.split('=')[0].trim();
            if (!cookieName.match(gaCookieRegex)) {
                return;
            }

            CookieStorageHelper.removeItem(cookieName);
        });
    }

    disableEvents() {
        this.events.forEach(event => {
            event.disable();
        });
    }

    /**
     * @param {Object} updatedCookies
     * @private
     */
    _updateConsent(updatedCookies) {
        if (Object.keys(updatedCookies).length === 0) {
            return;
        }

        const consentUpdateConfig = {};

        if (Object.prototype.hasOwnProperty.call(updatedCookies, this.cookieEnabledName)) {
            consentUpdateConfig['analytics_storage'] = updatedCookies[this.cookieEnabledName] ? 'granted' : 'denied';
        }

        if (Object.prototype.hasOwnProperty.call(updatedCookies, this.cookieAdsEnabledName)) {
            consentUpdateConfig['ad_storage'] = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
            consentUpdateConfig['ad_user_data'] = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
            consentUpdateConfig['ad_personalization'] = updatedCookies[this.cookieAdsEnabledName] ? 'granted' : 'denied';
        }

        if (Object.keys(consentUpdateConfig).length === 0) {
            return;
        }

        gtag('consent', 'update', consentUpdateConfig);
    }

    /**
     * @private
     */
    _getGclidStorageKey() {
        return 'google-analytics-' + (window.salesChannelId || '') + '-gclid';
    }
}
