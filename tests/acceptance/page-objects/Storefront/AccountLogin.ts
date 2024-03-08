import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class AccountLoginPage implements PageObject {
    public readonly emailInput: Locator;
    public readonly passwordInput: Locator;
    public readonly loginButton: Locator;
    public readonly logoutLink: Locator;
    public readonly successAlert: Locator;

    // Inputs for registration
    public readonly personalFormArea: Locator;
    public readonly billingAddressFormArea: Locator;
    public readonly firstNameInput: Locator;
    public readonly lastNameInput: Locator;
    public readonly registerEmailInput: Locator;
    public readonly registerPasswordInput: Locator;
    public readonly streetAddressInput: Locator;
    public readonly cityInput: Locator;
    public readonly countryInput: Locator;
    public readonly registerButton: Locator;

    constructor(public readonly page: Page) {
        this.emailInput = page.getByLabel('Your email address');
        this.passwordInput = page.getByLabel('Your password');
        this.loginButton = page.getByRole('button', { name: 'Log in' });
        this.logoutLink = page.getByRole('link', { name: 'Log out'});

        this.personalFormArea = page.locator('.register-personal');
        this.billingAddressFormArea = page.locator('.register-billing');
        this.firstNameInput = this.personalFormArea.getByLabel('First name*');
        this.lastNameInput = this.personalFormArea.getByLabel('Last name*');
        this.registerEmailInput = this.personalFormArea.getByLabel('Email address*');
        this.registerPasswordInput = this.personalFormArea.getByLabel('Password*');
        this.streetAddressInput = this.billingAddressFormArea.getByLabel('Street address*');
        this.cityInput = this.billingAddressFormArea.getByLabel('City*');
        this.countryInput = this.billingAddressFormArea.getByLabel('Country*');
        this.registerButton = page.getByRole('button', { name: 'Continue' });
        this.logoutLink = page.getByRole('link', { name: 'Log out'});
        this.successAlert = page.getByText('Successfully logged out.');
    }

    async goTo() {
        await this.page.goto('account/login');
    }
}
