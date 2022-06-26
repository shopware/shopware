import AccountPageObject from '../../../support/pages/account.page-object';
import SettingsPageObject from "../../../support/pages/module/sw-settings.page-object";

describe('Account: Handle addresses as new formatting', () => {
    beforeEach(() => {
        return cy.createCustomerFixtureStorefront().then(() => {
            return cy.clearCacheAdminApi('DELETE', `api/_action/cache`);
        });
    });

    it.skip('@customer @package: Display address as new formatting', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');

        // Login
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
        cy.get('#loginPassword').typeAndCheckStorefront('shopware');
        cy.get('.login-submit [type="submit"]').click();

        // Add address form
        cy.get('.account-content .account-aside-item[title="Addresses"]')
            .should('be.visible')
            .click();

        cy.get('a[href="/account/address/create"]').click();
        cy.get('.account-address-form').should('be.visible');

        // Fill in and submit address
        cy.get('#addresspersonalSalutation').typeAndSelect('Mr.');
        cy.get('#addresspersonalFirstName').typeAndCheckStorefront('P.  ');
        cy.get('#addresspersonalLastName').typeAndCheckStorefront('Sherman');
        cy.get('#addressAddressStreet').typeAndCheckStorefront('42 Wallaby Way');
        cy.get('#addressAddressZipcode').typeAndCheckStorefront('2000');
        cy.get('#addressAddressCity').typeAndCheckStorefront('Sydney');
        cy.get('#addressAddressCountry').typeAndSelect('Germany');
        cy.get('.address-form-submit').click();

        // Verify new address
        cy.get('.alert-success .alert-content').contains('Address has been saved.');

        cy.get('.address-list > :nth-child(2) > :nth-child(2)').contains('Sherman');

        // Set new address as shipping address
        cy.get('.address-list > :nth-child(2) > :nth-child(2)').within(() => {
            cy.contains('Set as default billing').click();
        });

        cy.visit('/account/address');

        cy.get('.address-list .address-card').eq(0).get('.address p').contains('Mr. P. Sherman');
        cy.get('.address-list .address-card').eq(0).get('.address p').contains('2000 Sydney');
        cy.get('.address-list .address-card').eq(0).get('.address p').contains('Germany');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/country/*`,
            method: 'PATCH'
        }).as('saveCountry');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
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
        cy.get('.sw-settings-country__address-handling-tab').click();

        cy.get('.sw-settings-country-address-handling__use-default-address-format .sw-field--switch__input').click();
        cy.get('.sw-code-editor textarea').clear({force: true});
        cy.get('.sw-code-editor').type('{{firstName}} \n{{lastName}}', { parseSpecialCharSequences: false });

        cy.get(settingPage.elements.countrySaveAction).click();

        cy.wait('@saveCountry')
            .its('response.statusCode').should('equal', 204);

        cy.visit('/account/address');

        cy.get('.address-list .address-card').eq(0).get('.address p').contains('P.');
        cy.get('.address-list .address-card').eq(0).get('.address p').contains('Sherman');
        cy.get('.address-list .address-card').eq(0).get('.address p').should('not.contain', '2000');
        cy.get('.address-list .address-card').eq(0).get('.address p').should('not.contain', 'Germany');
    });
});
