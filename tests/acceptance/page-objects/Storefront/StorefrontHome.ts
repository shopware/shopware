import { PageObject } from '@fixtures/PageObject';
import { Locator, Page } from '@playwright/test';

export class StorefrontHomePage implements PageObject {

    public readonly productImages: Locator;

    constructor(public readonly page: Page) {

        this.productImages = page.locator('.product-image-link');
    }

    async goTo() {
        await this.page.goto('./');
    }
}
