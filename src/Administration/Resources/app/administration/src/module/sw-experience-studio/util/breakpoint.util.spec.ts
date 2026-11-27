/**
 * @sw-package discovery
 */

import { resolveBreakpointValue } from './breakpoint.util';

describe('module/sw-experience-studio/util/breakpoint.util', () => {
    it('keeps an explicit zero ahead of fallback values', () => {
        const value = resolveBreakpointValue({ xs: 0, sm: 2, lg: 4 }, 'xs', { xs: ['sm', 'lg'] });

        expect(value).toBe(0);
    });

    it('uses the first configured fallback with a value, even when it is false', () => {
        const value = resolveBreakpointValue({ xl: false, lg: true }, 'xxl', { xxl: ['xl', 'lg'] });

        expect(value).toBe(false);
    });

    it('skips null values at both the requested breakpoint and its first fallback', () => {
        const value = resolveBreakpointValue({ xxl: null, xl: null, lg: '24px' }, 'xxl', { xxl: ['xl', 'lg'] });

        expect(value).toBe('24px');
    });

    it('only checks the fallback list configured for the requested breakpoint', () => {
        const value = resolveBreakpointValue({ lg: '24px' }, 'xs', { xs: ['sm'], sm: ['lg'] });

        expect(value).toBeUndefined();
    });
});
