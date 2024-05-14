import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class FlowBuilderCreatePage implements PageObject {

    public readonly saveButton: Locator;
    public readonly header: Locator;

    constructor(public readonly page: Page) {
        this.saveButton = page.locator('.sw-flow-detail__save');
        this.header = page.locator('h2');
    }

    async goTo() {
        await this.page.goto(`#/sw/flow/create/general`);
    }
}
