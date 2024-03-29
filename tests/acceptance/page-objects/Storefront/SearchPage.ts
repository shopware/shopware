import type { Page } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class SearchPage implements PageObject {

    private readonly productImages;

    constructor(public readonly page: Page) {

        this.productImages = page.locator('.product-image-link');
    }

    async goTo() {
        const url = `search?search`;
        await this.page.goto(url);
    }
}
