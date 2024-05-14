import { PageObject } from '@fixtures/PageObject';
import { Locator, Page } from '@playwright/test';

export class CheckoutRegisterPage implements PageObject {

    public readonly cartLineItemImages: Locator;

    constructor(public readonly page: Page) {

        this.cartLineItemImages = page.locator('.line-item-img-link');
    }

    async goTo() {
        await this.page.goto('checkout/register');
    }
}
