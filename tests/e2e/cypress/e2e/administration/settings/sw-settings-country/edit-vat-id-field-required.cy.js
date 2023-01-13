import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Country: Test can setting VAT id field required', () => {
    beforeEach(() => {
        cy.createDefaultFixture('country');
    });

    it('@settings: can setting VAT id field required', { tags: ['pa-system-settings'] }, () => {
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
        cy.contains('.smart-bar__header', 'Countries');

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
        cy.get(settingPage.elements.countrySaveAction).click();

        cy.wait('@saveCountry')
            .its('response.statusCode').should('equal', 204);

        cy.visit('/account/login');
        const accountTypeSelector = '.register-form select[name="accountType"]';

        cy.get(accountTypeSelector).should('be.visible');
        cy.get(accountTypeSelector).select('Commercial');
        cy.get('#vatIds').should('be.visible');

        cy.get('select[name="billingAddress[countryId]"]').select('Germany');
        cy.contains('.form-label[for="vatIds"]', '*');

        cy.get('.register-submit [type="submit"]').click();
        cy.get('[name="vatIds[]"]:invalid').should('be.visible');
    });
});
