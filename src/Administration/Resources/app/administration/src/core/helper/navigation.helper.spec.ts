/**
 * @sw-package framework
 */
import { reloadPage, navigateTo, getLocationHref, getLocationHostname, getLocationOrigin } from './navigation.helper';

describe('src/core/helper/navigation.helper.ts', () => {
    it('should return window.location.href via getLocationHref', () => {
        expect(getLocationHref()).toBe(window.location.href);
    });

    it('should return window.location.hostname via getLocationHostname', () => {
        expect(getLocationHostname()).toBe(window.location.hostname);
    });

    it('should return window.location.origin via getLocationOrigin', () => {
        expect(getLocationOrigin()).toBe(window.location.origin);
    });

    it('should expose reloadPage as a function', () => {
        expect(typeof reloadPage).toBe('function');
    });

    it('should expose navigateTo as a function', () => {
        expect(typeof navigateTo).toBe('function');
    });
});
