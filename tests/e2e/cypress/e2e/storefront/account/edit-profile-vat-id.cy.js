import AccountPageObject from '../../../support/pages/account.page-object';
import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

/**
 * @package checkout
 */
describe('Account: Edit profile\'s Vat Id', () => {
    beforeEach(() => {
        return cy.createCustomerFixtureStorefront().then(() => {
            return cy.createDefaultFixture('country');
        });
    });

    it('@customer @package: Update profile', { tags: ['pa-customers-orders'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/login/registration/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
        }).as('saveSettings');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'PATCH',
        }).as('saveCountry');

        cy.get('input[name="core.loginRegistration.showAccountTypeSelection"]').scrollIntoView();
        cy.get('input[name="core.loginRegistration.showAccountTypeSelection"]').should('exist');

        cy.get('input[name="core.loginRegistration.showAccountTypeSelection"]').click().should('have.value', 'on');
        cy.get('.smart-bar__content .sw-button--primary').click();

        cy.wait('@saveSettings')
            .its('response.statusCode').should('equal', 204);

        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        const settingPage = new SettingsPageObject();

        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-country').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.smart-bar__header').contains('Countries');

        // // should wait for search result
        cy.intercept({
            method: 'POST',
            url: '/api/search/country',
        }).as('searchCountries');

        // find a country with the name is "Germany"
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.get('input.sw-search-bar__input').type('{esc}');

        // choose "Germany"
        cy.get(`${settingPage.elements.dataGridRow}--0 ${settingPage.elements.countryColumnName} a`).should('be.visible');
        cy.get(`${settingPage.elements.dataGridRow}--0 ${settingPage.elements.countryColumnName} a`).click();

        cy.wait('@searchCountries');
        cy.get('.sw-settings-country-general__vat-id-required .sw-field--switch__input').click();
        cy.get('.sw-settings-country-general__field-check-vatid-format .sw-field--switch__input').click();
        cy.get(settingPage.elements.countrySaveAction).click();

        cy.wait('@saveCountry')
            .its('response.statusCode').should('equal', 204);

        cy.visit('/account/login');

        const page = new AccountPageObject();

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain('Overview');
        });

        cy.visit('/account/profile');
        const accountTypeSelector = 'select[name="accountType"]';
        const companySelector = 'input[name="company"]';
        const vatIdsSelector = 'input#vatIds';
        cy.get(accountTypeSelector).should('be.visible');

        cy.get(accountTypeSelector).typeAndSelect('Private');
        cy.get(companySelector).should('not.be.visible');
        cy.get(vatIdsSelector).should('not.be.visible');

        cy.get(accountTypeSelector).typeAndSelect('Commercial');
        cy.get(companySelector).should('be.visible');
        cy.get(companySelector).type('Company Testing');

        cy.get(vatIdsSelector).should('be.visible');

        cy.get(vatIdsSelector).clear();
        cy.get('#profilePersonalForm button[type="submit"]').click();
        cy.get('.invalid-feedback').contains('Input should not be empty.').should('be.visible');

        cy.get(vatIdsSelector).clearTypeAndCheck('wrong-format');
        cy.get('#profilePersonalForm button[type="submit"]').click();
        cy.get('.invalid-feedback').contains('The VAT Reg.No. you have entered does not have the correct format.').should('be.visible');

        cy.get(vatIdsSelector).clearTypeAndCheck('DE123456789');
        cy.get('#profilePersonalForm button[type="submit"]').click();
        cy.get('.alert-success .alert-content').contains('Profile has been updated.');
    });
});
