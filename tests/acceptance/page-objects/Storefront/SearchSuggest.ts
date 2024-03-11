import type { Page } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class SearchSuggest implements PageObject {

    private readonly searchSuggestLineItemImages;

    constructor(public readonly page: Page) {

        this.searchSuggestLineItemImages = page.locator('.search-suggest-product-image-container');
    }

    async goTo() {
        const url = `suggest?search`;
        await this.page.goto(url);
    }
}
