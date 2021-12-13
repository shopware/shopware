/// <reference types="Cypress" />

describe('Storefront: test registration with country settings & invalid inputs', () => {

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.authenticate().then((result) => {
                const requestConfig = {
                    headers: {
                        Authorization: `Bearer ${result.access}`
                    },
                    method: 'POST',
                    url: `api/_action/system-config/batch`,
                    body: {
                        null: {
                            'core.loginRegistration.showAccountTypeSelection': true
                        }
                    }
                };
                return cy.request(requestConfig);
            });
        });
    });

    it('@package: should not validate registration with wrong VAT Reg.No format', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('getCountrySettings');
        cy.intercept({
            url: `/account/register`,
            method: 'POST'
        }).as('registerCustomer');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Netherlands').click();
        cy.get('input[name="sw-field--country-checkVatIdPattern"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        // Should not validate registration with wrong VAT-ID format
        cy.visit('/account/register');
        cy.url().should('include', '/account/register');
        cy.get('#accountType').select('Commercial');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#billingAddresscompany').typeAndCheckStorefront('shopware AG');
        cy.get('#vatIds').typeAndCheckStorefront('DE12345');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 200);
        cy.get('.invalid-feedback').contains('does not have the correct format').should('be.visible');
        cy.url().should('include', 'account/register');
    });

    it('@package: should not validate registration with empty VAT-ID', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('getCountrySettings');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Netherlands').click();
        cy.get('[name="sw-field--country-vatIdRequired"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        // Should not validate registration with empty VAT-ID
        cy.visit('/account/register');
        cy.url().should('include', '/account/register');
        cy.get('#accountType').select('Commercial');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#billingAddresscompany').typeAndCheckStorefront('shopware AG');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('[for="vatIds"]').scrollIntoView().contains('VAT Reg.No.*').should('be.visible');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.url().should('include', 'account/register');
    });

    it('@package: should not validate registration without required state selection', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST'
        }).as('getCountrySettings');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.goToSalesChannelDetail('E2E install test')
            .selectCountryForSalesChannel('Germany');
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Germany').click();
        cy.get('[name="sw-field--country-forceStateInRegistration"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-loader').should('not.exist');

        // Should not validate registration without state selection
        cy.visit('/account/register');
        cy.url().should('include', '/account/register');
        cy.get('#accountType').select('Private');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Germany');
        cy.get('#billingAddressAddressCountryState').should('be.visible');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.url().should('include', 'account/register');
    });
});
