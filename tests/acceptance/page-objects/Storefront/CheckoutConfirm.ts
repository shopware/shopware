import type { Page, Locator } from '@playwright/test';
import { expect } from '@playwright/test';

export class CheckoutConfirmPage {
    public readonly headline: Locator;
    public readonly termsAndConditionsCheckbox: Locator;
    public readonly grantTotalPrice: Locator;
    public readonly submitOrderButton: Locator;

    /**
     * Payment options
     */
    public readonly paymentCashOnDelivery: Locator;
    public readonly paymentPaidInAdvance: Locator;
    public readonly paymentInvoice: Locator;

    /**
     * Shipping options
     */
    public readonly shippingStandard: Locator;
    public readonly shippingExpress: Locator;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: 'Complete order' });
        this.termsAndConditionsCheckbox = page.getByLabel('I have read and accepted the general terms and conditions.');
        this.grantTotalPrice = page.locator(`dt:has-text('Grand total') + dd`);
        this.submitOrderButton = page.getByRole('button', { name: 'Submit order' });

        this.paymentCashOnDelivery = page.getByLabel('Cash on delivery');
        this.paymentPaidInAdvance = page.getByLabel('Paid in advance');
        this.paymentInvoice = page.getByLabel('Invoice');

        this.shippingStandard = page.getByLabel('Standard');
        this.shippingExpress = page.getByLabel('Express');
    }

    async goto() {
        await this.page.goto('checkout/confirm');
    }

    async selectCashOnDeliveryPaymentOption() {
        await this.paymentCashOnDelivery.check();
        await expect(this.paymentCashOnDelivery).toBeChecked();
    }

    async selectPaidInAdvancePaymentOption() {
        await this.paymentPaidInAdvance.check();
        await expect(this.paymentPaidInAdvance).toBeChecked();
    }

    async selectInvoicePaymentOption() {
        await this.paymentInvoice.check();
        await expect(this.paymentInvoice).toBeChecked();
    }

    async selectStandardShippingOption() {
        await this.shippingStandard.check();
        await expect(this.shippingStandard).toBeChecked();
    }

    async selectExpressShoppingOption() {
        await this.shippingExpress.check();
        await expect(this.shippingExpress).toBeChecked();
    }
}
