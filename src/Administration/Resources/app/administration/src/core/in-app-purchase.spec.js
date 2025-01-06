/**
 * @package checkout
 */

import InAppPurchase from './in-app-purchase';

describe('InAppPurchase', () => {
    it('should initialize with an empty object', () => {
        expect(InAppPurchase.all()).toEqual({});
        expect(InAppPurchase.flattened()).toEqual([]);
    });

    it('should add new in-app purchases', () => {
        Shopware.State.get('context').app.config.inAppPurchases = {
            extension1: [
                'identifier1',
            ],
        };

        expect(InAppPurchase.all()).toEqual({
            extension1: [
                'identifier1',
            ],
        });
    });

    it('should get in-app purchases by identifier id', () => {
        Shopware.State.get('context').app.config.inAppPurchases = {
            extension1: [
                'identifier1',
                'identifier2',
            ],
            extension2: [
                'identifier2',
            ],
        };

        expect(InAppPurchase.all()).toEqual({
            extension1: [
                'identifier1',
                'identifier2',
            ],
            extension2: [
                'identifier2',
            ],
        });
        expect(InAppPurchase.flattened()).toEqual([
            'extension1-identifier1',
            'extension1-identifier2',
            'extension2-identifier2',
        ]);
        expect(InAppPurchase.getByExtension('extension1')).toEqual([
            'identifier1',
            'identifier2',
        ]);
    });

    it('should return an empty object if no in-app purchases for the given identifier id', () => {
        Shopware.State.get('context').app.config.inAppPurchases = {
            extension1: [
                'identifier1',
            ],
        };

        expect(InAppPurchase.getByExtension('extension2')).toEqual([]);
    });

    it('should check if an in-app purchase is active', () => {
        Shopware.State.get('context').app.config.inAppPurchases = {
            extension1: [
                'identifier1',
            ],
        };

        expect(InAppPurchase.isActive('extension1', 'identifier1')).toBe(true);
        expect(InAppPurchase.isActive('extension1', 'identifier2')).toBe(false);
    });
});
