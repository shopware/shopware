/* eslint-disable */
import AffiliateTrackingPlugin from 'src/plugin/affiliate-tracking/affiliate-tracking.plugin';
import CookieStorage from 'src/helper/storage/cookie-storage.helper';

/**
 * @sw-package framework
 */
describe('AffiliateTrackingPlugin', () => {
    let plugin;
    let element;
    
    beforeEach(() => {
        delete window.location;
        window.location = { search: '' };
        
        jest.spyOn(CookieStorage, 'setItem').mockImplementation(() => {});
        
        element = document.createElement('div');
        plugin = new AffiliateTrackingPlugin(element);
    });
    
    afterEach(() => {
        jest.restoreAllMocks();
    });
    
    test('plugin initializes correctly', () => {
        expect(plugin).toBeTruthy();
        expect(plugin.options).toBeDefined();
    });
    
    test('sets affiliate code cookie when URL parameter exists', () => {
        window.location.search = '?affiliateCode=test-affiliate';
        plugin.init();
        
        expect(CookieStorage.setItem).toHaveBeenCalledWith(
            plugin.options.affiliateCodeCookie,
            'test-affiliate',
            plugin.options.cookieExpiration
        );
    });
    
    test('sets campaign code cookie when URL parameter exists', () => {
        window.location.search = '?campaignCode=test-campaign';
        plugin.init();
        
        expect(CookieStorage.setItem).toHaveBeenCalledWith(
            plugin.options.campaignCodeCookie,
            'test-campaign',
            plugin.options.cookieExpiration
        );
    });
    
    test('sets both cookies when both URL parameters exist', () => {
        window.location.search = '?affiliateCode=test-affiliate&campaignCode=test-campaign';
        plugin.init();
        
        expect(CookieStorage.setItem).toHaveBeenCalledTimes(2);
        expect(CookieStorage.setItem).toHaveBeenCalledWith(
            plugin.options.affiliateCodeCookie,
            'test-affiliate',
            plugin.options.cookieExpiration
        );
        expect(CookieStorage.setItem).toHaveBeenCalledWith(
            plugin.options.campaignCodeCookie,
            'test-campaign',
            plugin.options.cookieExpiration
        );
    });
    
    test('does not set cookies when URL parameters do not exist', () => {
        window.location.search = '?otherParam=value';
        plugin.init();
        
        expect(CookieStorage.setItem).not.toHaveBeenCalled();
    });
});
