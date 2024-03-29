import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AdminOrderDetailPage implements PageObject {

    public readonly orderData;
    public readonly dataGridContextButton: Locator;
    public readonly orderTag: Locator;

    constructor(public readonly page: Page, orderData) {

        this.orderData = orderData;
        this.dataGridContextButton = page.locator('.sw-data-grid__actions-menu').and(page.getByRole('button'));
        this.orderTag = page.locator('.sw-select-selection-list__item');

    }

    async goTo() {
        await this.page.goto(`#/sw/order/detail/${this.orderData.id}/general`);
    }

}
