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
import ViewItemEvent from 'src/plugin/google-analytics/events/view-item.event';
import ViewItemListEvent from 'src/plugin/google-analytics/events/view-item-list.event';
import ViewSearchResultsEvent from 'src/plugin/google-analytics/events/view-search-results';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';

export default class GoogleAnalyticsPlugin extends Plugin
{
    init() {
        this.cookieEnabledName = 'google-analytics-enabled';

        this.handleCookieChangeEvent();

        if (!CookieStorageHelper.getItem(this.cookieEnabledName)) {
            return;
        }

        this.startGoogleAnalytics();
    }

    startGoogleAnalytics() {
        window.gtagCallback();

        this.controllerName = window.controllerName;
        this.actionName = window.actionName;
        this.events = [];

        this.registerDefaultEvents();
        this.handleEvents();
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
}
