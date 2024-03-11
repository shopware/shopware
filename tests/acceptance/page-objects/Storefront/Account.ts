import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AccountPage implements PageObject {
    public readonly headline: Locator;
    public readonly personalDataCardTitle: Locator;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: 'Overview' });
        this.personalDataCardTitle = page.getByRole('heading', { name: 'Personal data' });
    }

    async goTo() {
        await this.page.goto('account');
    }
}
