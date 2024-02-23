import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class CheckoutConfirmPage implements PageObject {
    public readonly headline: Locator;
    public readonly termsAndConditionsCheckbox: Locator;
    public readonly grandTotalPrice: Locator;
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

    /**
     * Product details
     */
    public readonly cartLineItemImages: Locator;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: 'Complete order' });
        this.termsAndConditionsCheckbox = page.getByLabel('I have read and accepted the general terms and conditions.');
        this.grandTotalPrice = page.locator(`dt:has-text('Grand total') + dd`);
        this.submitOrderButton = page.getByRole('button', { name: 'Submit order' });

        this.paymentCashOnDelivery = page.getByLabel('Cash on delivery');
        this.paymentPaidInAdvance = page.getByLabel('Paid in advance');
        this.paymentInvoice = page.getByLabel('Invoice');

        this.shippingStandard = page.getByLabel('Standard');
        this.shippingExpress = page.getByLabel('Express');

        this.cartLineItemImages = page.locator('.line-item-img-link');
    }

    async goTo() {
        await this.page.goto('checkout/confirm');
    }
}
