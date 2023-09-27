import type { Page, Locator } from '@playwright/test';

export class CheckoutFinishPage {
    public readonly headline: Locator;
    public readonly orderNumberText: Locator;

    private readonly orderNumberRegex = /Your order number: #(\d+)/;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: 'Thank you for your order' });
        this.orderNumberText = page.getByText(this.orderNumberRegex);
    }

    async getOrderNumber() {
        const orderNumberText = await this.orderNumberText.textContent();
        const [, orderNumber] = orderNumberText.match(this.orderNumberRegex);

        return orderNumber;
    }

    getOrderId() {
        const url = this.page.url();
        const [, searchString] = url.split('?');
        const params = new URLSearchParams(searchString);

        return params.get('orderId');
    }
}
