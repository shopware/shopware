/**
 * @sw-package checkout
 */

import CUSTOMER from './customer.constant';

describe('core/constant/customer.constant', () => {
    it('carries the two account types the data abstraction layer stores', () => {
        expect(CUSTOMER.ACCOUNT_TYPE_PRIVATE).toBe('private');
        expect(CUSTOMER.ACCOUNT_TYPE_BUSINESS).toBe('business');
    });

    it('cannot be changed by a consumer', () => {
        expect(Object.isFrozen(CUSTOMER)).toBe(true);
    });

    it('is the same object the customer module re-exports', async () => {
        const moduleConstant = (await import('src/module/sw-customer/constant/sw-customer.constant')).default;

        expect(moduleConstant).toBe(CUSTOMER);
    });
});
