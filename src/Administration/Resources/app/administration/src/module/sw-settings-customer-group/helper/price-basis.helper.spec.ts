/**
 * @sw-package discovery
 */
import { getEffectivePriceBasis } from './price-basis.helper';

describe('src/module/sw-settings-customer-group/helper/price-basis.helper', () => {
    it('keeps the stored net basis when the tax display is gross', () => {
        expect(getEffectivePriceBasis({ displayGross: true, priceBasis: 'net' })).toBe('net');
    });

    it('keeps the stored gross basis when the tax display is net', () => {
        expect(getEffectivePriceBasis({ displayGross: false, priceBasis: 'gross' })).toBe('gross');
    });

    it('derives the gross basis from the tax display when no basis is stored', () => {
        expect(getEffectivePriceBasis({ displayGross: true, priceBasis: null })).toBe('gross');
    });

    it('derives the net basis from the tax display when no basis is stored', () => {
        expect(getEffectivePriceBasis({ displayGross: false, priceBasis: null })).toBe('net');
    });

    it('derives the net basis for an undefined customer group', () => {
        expect(getEffectivePriceBasis(undefined)).toBe('net');
    });
});
