import type { Locator, Page } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class SearchSuggestPage implements PageObject {

    public readonly searchSuggestLineItemImages: Locator;

    constructor(public readonly page: Page) {

        this.searchSuggestLineItemImages = page.locator('.search-suggest-product-image-container');
    }

    async goTo() {
        const url = `suggest?search`;
        await this.page.goto(url);
    }
}
