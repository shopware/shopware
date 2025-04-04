/**
 * @sw-package framework
 */
import Plugin from 'src/plugin-system/plugin.class';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';

/**
 * This plugin stores affiliate and campaign codes from URL parameters in cookies.
 * This ensures the codes persist across different browser sessions and visits.
 */
export default class AffiliateTrackingPlugin extends Plugin {
    static options = {
        /**
         * URL parameter name for affiliate code
         */
        affiliateCodeParam: 'affiliateCode',
        
        /**
         * URL parameter name for campaign code
         */
        campaignCodeParam: 'campaignCode',
        
        /**
         * Cookie name for affiliate code
         */
        affiliateCodeCookie: 'affiliate-code',
        
        /**
         * Cookie name for campaign code
         */
        campaignCodeCookie: 'campaign-code',
        
        /**
         * Cookie expiration time in days
         */
        cookieExpiration: 30,
    };

    init() {
        const urlParams = new URLSearchParams(window.location.search);
        
        const affiliateCode = urlParams.get(this.options.affiliateCodeParam);
        if (affiliateCode) {
            this._setAffiliateCookie(affiliateCode);
        }
        
        const campaignCode = urlParams.get(this.options.campaignCodeParam);
        if (campaignCode) {
            this._setCampaignCookie(campaignCode);
        }
    }

    /**
     * Set affiliate code cookie
     * 
     * @param {string} code
     * @private
     */
    _setAffiliateCookie(code) {
        CookieStorage.setItem(this.options.affiliateCodeCookie, code, this.options.cookieExpiration);
    }

    /**
     * Set campaign code cookie
     * 
     * @param {string} code
     * @private
     */
    _setCampaignCookie(code) {
        CookieStorage.setItem(this.options.campaignCodeCookie, code, this.options.cookieExpiration);
    }
}
