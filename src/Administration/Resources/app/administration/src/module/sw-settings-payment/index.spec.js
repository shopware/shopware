/**
 * @sw-package checkout
 */

import './index';

jest.mock('./acl', () => jest.fn());

const { Module } = Shopware;

describe('src/module/sw-settings-payment/index.js', () => {
    it('should require editor permissions for the payment method detail route', () => {
        const module = Module.getModuleRegistry().get('sw-settings-payment');
        const route = module.routes.get('sw.settings.payment.detail');

        expect(route.meta.privilege).toBe('payment.editor');
    });
});
