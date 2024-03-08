import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import { components } from '@shopware/api-client/admin-api-types';

export class AdminCustomerDetailPage implements PageObject {
    public readonly customerId;
    
    public readonly editButton: Locator;
    public readonly generalTab: Locator;
    public readonly accountCard: Locator;

    constructor(public readonly page: Page, customer :components['schemas']['Customer'] ) {
        this.customerId = customer.id;
        this.editButton = page.getByRole('button', { name: 'Edit' });
        this.generalTab = page.getByRole('link', { name: 'General' });
        this.accountCard = page.locator('.sw-customer-card')
    }

    async goTo() {
        await this.page.goto(`#/sw/customer/detail/${this.customerId}/base`);
    }
}