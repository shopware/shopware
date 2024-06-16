/**
 * @package admin
 */

import InAppPurchase from './in-app-purchase';

describe('InAppPurchase', () => {
    it('should initialize with an empty object', () => {
        expect(InAppPurchase.getAll()).toEqual({});
    });

    it('should add new in-app purchases', () => {
        InAppPurchase.init({ identifier1: 'iap1' });
        expect(InAppPurchase.getAll()).toEqual({ identifier1: 'iap1' });
    });

    it('should get in-app purchases by identifier id', () => {
        InAppPurchase.init({ identifier1: 'iap1', identifier2: 'iap2', identifier3: 'iap2', identifier4: 'iap1' });
        expect(InAppPurchase.getByExtension('iap2')).toEqual([
            'identifier2',
            'identifier3',
        ]);
    });

    it('should return an empty object if no in-app purchases for the given identifier id', () => {
        InAppPurchase.init({ identifier1: 'iap1', identifier2: 'iap2' });
        expect(InAppPurchase.getByExtension('identifier3')).toEqual([]);
    });

    it('should check if an in-app purchase is active', () => {
        InAppPurchase.init({ identifier1: 'iap1', identifier2: 'iap2' });
        expect(InAppPurchase.isActive('iap1')).toBe(true);
        expect(InAppPurchase.isActive('iap3')).toBe(false);
    });
});
