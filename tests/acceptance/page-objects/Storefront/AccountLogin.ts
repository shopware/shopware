import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AccountLoginPage implements PageObject {
    public readonly emailInput: Locator;
    public readonly passwordInput: Locator;
    public readonly loginButton: Locator;

    constructor(public readonly page: Page) {
        this.emailInput = page.getByLabel('Your email address');
        this.passwordInput = page.getByLabel('Your password');
        this.loginButton = page.getByRole('button', { name: 'Log in' });
    }

    async goTo() {
        await this.page.goto('account/login');
    }
}
