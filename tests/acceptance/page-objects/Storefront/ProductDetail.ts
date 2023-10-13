import type { Page, Locator } from '@playwright/test';

export class ProductDetailPage {
    public readonly addToCartButton: Locator;

    public readonly offCanvasCartTitle: Locator;
    public readonly offCanvasCart: Locator;

    private readonly product;

    constructor(
        public readonly page: Page,
        product
    ) {
        this.addToCartButton = page.getByRole('button', { name: 'Add to shopping cart' });
        this.offCanvasCartTitle = page.getByText('Shopping cart');
        this.offCanvasCart = page.getByRole('dialog');

        this.product = product;
    }

    async goto() {
        const url = `${this.product.translated.name.replaceAll('_', '-')}/${this.product.productNumber}`;

        await this.page.goto(url);
    }
}
