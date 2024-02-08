import GoogleAnalyticsPlugin from 'src/plugin/google-analytics/google-analytics.plugin';
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
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';

describe('plugin/google-analytics/google-analytics.plugin', () => {
    beforeEach(() => {
        window.useDefaultCookieConsent = true;
        window.gtag = jest.fn();
        window.gtagTrackingId = 'GA-12345-6';
        window.gtagURL = `https://www.googletagmanager.com/gtag/js?id=${window.gtagTrackingId}`;
        window.gtagConfig = {
            'anonymize_ip': '1',
            'cookie_domain': 'none',
            'cookie_prefix': '_swag_ga',
        };

        document.$emitter.unsubscribe(COOKIE_CONFIGURATION_UPDATE);
    });

    afterEach(() => {
        // Reset all cookies after each test
        document.cookie = '';
        document.head.innerHTML = '';

        jest.clearAllMocks();
        window.gtag.mockRestore();
    });

    test('initialize Google Analytics plugin', () => {
        expect(new GoogleAnalyticsPlugin(document)).toBeInstanceOf(GoogleAnalyticsPlugin);
    });

    test('starts Google Analytics when allowance cookie is set', () => {
        // Set the Google Analytics cookie
        Object.defineProperty(document, 'cookie', {
            writable: true,
            value: 'google-analytics-enabled=1',
        });

        const startGoogleAnalyticsSpy = jest.spyOn(GoogleAnalyticsPlugin.prototype, 'startGoogleAnalytics');
        new GoogleAnalyticsPlugin(document);

        expect(startGoogleAnalyticsSpy).toHaveBeenCalledTimes(1);

        // Verify gtag is called with expected parameters from window object
        expect(window.gtag).toHaveBeenCalledTimes(2);
        expect(window.gtag).toHaveBeenCalledWith('js', expect.any(Date));
        expect(window.gtag).toHaveBeenCalledWith('config', window.gtagTrackingId, window.gtagConfig);

        // Verify the tag manager script is injected into the <head> with correct src
        expect(document.getElementsByTagName('script')[0].src).toBe(window.gtagURL);
    });

    test('does not inject Google Analytics script when allowance cookie is not set', () => {
        // No cookie is set before the plugin is initialized
        new GoogleAnalyticsPlugin(document);

        // Verify gtag is not called
        expect(window.gtag).not.toHaveBeenCalled();

        // Verify that no analytics <script> is injected
        expect(document.getElementsByTagName('script').length).toBe(0);
    });

    test('registers all default Google Analytics events allowance cookie is set', () => {
        // Set the Google Analytics cookie
        Object.defineProperty(document, 'cookie', {
            writable: true,
            value: 'google-analytics-enabled=1',
        });

        const googleAnalyticsPlugin = new GoogleAnalyticsPlugin(document);

        // Verify all default events are registered
        expect(googleAnalyticsPlugin.events).toEqual([
            new AddToCartEvent(),
            new AddToCartByNumberEvent(),
            new BeginCheckoutEvent(),
            new BeginCheckoutOnCartEvent(),
            new CheckoutProgressEvent(),
            new LoginEvent(),
            new PurchaseEvent(),
            new RemoveFromCartEvent(),
            new SearchAjaxEvent(),
            new SignUpEvent(),
            new ViewItemEvent(),
            new ViewItemListEvent(),
            new ViewSearchResultsEvent(),
        ]);
    });

    test('sets the correct google consent when cookie update event is fired', () => {
        // Set the Google Analytics cookie
        Object.defineProperty(document, 'cookie', {
            writable: true,
            value: 'google-analytics-enabled=1',
        });

        new GoogleAnalyticsPlugin(document)

        // Simulate cookie update event
        document.$emitter.publish(COOKIE_CONFIGURATION_UPDATE, {
            'google-analytics-enabled': true,
            'google-ads-enabled': true,
        });

        // Verify gtag consent update is called with expected parameters
        expect(window.gtag).toHaveBeenNthCalledWith(3, 'consent', 'update', {
            'ad_user_data': 'granted',
            'ad_personalization': 'granted',
            'ad_storage': 'granted',
            'analytics_storage': 'granted',
        });
    });
});