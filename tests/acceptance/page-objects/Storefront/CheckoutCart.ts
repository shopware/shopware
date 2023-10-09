import type { Page, Locator } from '@playwright/test';

export class CheckoutCartPage {
    public readonly headline: Locator;
    public readonly goToCheckoutButton: Locator;
    public readonly enterDiscountInput: Locator;
    public readonly grandTotalPrice: Locator;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: 'Shopping cart' });
        this.goToCheckoutButton = page.getByRole('link', { name: 'Go to checkout' });
        this.enterDiscountInput = page.getByLabel('Discount code');
        this.grandTotalPrice = page.locator('dt:has-text("Grand total") + dd');
    }

    async goto() {
        await this.page.goto('checkout/cart');
    }
}
