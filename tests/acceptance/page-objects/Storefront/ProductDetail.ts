import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class ProductDetailPage implements PageObject {
    public readonly addToCartButton: Locator;

    public readonly offCanvasCartTitle: Locator;
    public readonly offCanvasCart: Locator;
    public readonly offCanvasCartGoToCheckoutButton: Locator;
    public readonly quantitySelect: Locator;
    public readonly offCanvasStockReachedAlert: Locator;
    public readonly offCanvasSummaryTotalPrice: Locator;

    private readonly product;

    constructor(public readonly page: Page, product) {
        this.addToCartButton = page.getByRole('button', { name: 'Add to shopping cart' });
        this.offCanvasCartTitle = page.getByText('Shopping cart');
        this.offCanvasCart = page.getByRole('dialog');
        this.offCanvasCartGoToCheckoutButton = page.getByRole('link', { name: 'Go to checkout' });
        this.quantitySelect = page.getByLabel('Quantity', { exact: true });
        this.offCanvasStockReachedAlert = page.getByText('only available 1 times')
        this.offCanvasSummaryTotalPrice = page.locator('dt:has-text("Subtotal") + dd')

        this.product = product;
    }

    async goTo() {
        const url = `${this.product.translated.name.replaceAll('_', '-')}/${this.product.productNumber}`;

        await this.page.goto(url);
    }
}
