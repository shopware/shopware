/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Storefront: test registration with country settings & invalid inputs', () => {
    beforeEach(() => {
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`,
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.loginRegistration.showAccountTypeSelection': true,
                    },
                },
            };
            return cy.request(requestConfig);
        });
    });

    it('@package: should not validate registration with wrong VAT Reg.No format', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST',
        }).as('getCountrySettings');
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.contains(`.sw-data-grid__cell--name`, 'Netherlands').click();
        cy.get('input[name="sw-field--country-checkVatIdPattern"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
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
        cy.contains('.invalid-feedback', 'does not have the correct format').should('be.visible');
        cy.url().should('include', 'account/register');
    });

    it('@package: should not validate registration with empty VAT-ID', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST',
        }).as('getCountrySettings');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/country/**`,
            method: 'PATCH',
        }).as('saveCountrySettings');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Netherlands');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get(`.sw-data-grid__cell--name`).contains('Netherlands').click({ force: true });

        // Ensure we are on detail page of country "Netherlands"
        cy.contains('.smart-bar__header h2', 'Netherlands').should('be.visible');
        cy.get('[name="sw-field--country-vatIdRequired"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@saveCountrySettings').its('response.statusCode').should('equal', 204);
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Ensure vatIdRequired is checked
        cy.get('[name="sw-field--country-vatIdRequired"]').should('be.checked');

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
        cy.get('#vatIds').scrollIntoView();
        cy.get('#vatIds').should('have.attr', 'required');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.url().should('include', 'account/register');
    });

    it('@package: should not validate registration without required state selection', () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/country`,
            method: 'POST',
        }).as('getCountrySettings');

        // Country settings
        cy.visit(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-dashboard-index__welcome-title').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.goToSalesChannelDetail(Cypress.env('storefrontName'));
        cy.contains('.smart-bar__header', Cypress.env('storefrontName'));
        cy.selectCountryForSalesChannel('Germany');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/country/index`);
        cy.contains('.sw-page__smart-bar-amount', '250');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Germany');
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-version__title').click();
        cy.get(`.sw-data-grid__cell--name`).contains('Germany').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Country handling tab
        cy.get('.sw-settings-country__address-handling-tab').click();
        cy.get('[name="sw-field--country-postalCodeRequired"]').check();
        cy.get('[name="sw-field--country-checkPostalCodePattern"]').check();
        cy.get('[name="sw-field--country-checkAdvancedPostalCodePattern"]').check();

        cy.get('[name="sw-field--country-forceStateInRegistration"]').check();
        cy.get('.sw-button-process__content').click();
        cy.wait('@getCountrySettings').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
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
