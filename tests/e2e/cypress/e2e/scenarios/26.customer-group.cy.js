/// <reference types="Cypress" />

describe('Admin & Storefront: test customer group registration', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/customer/group/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@package: should register with new customer group', { tags: ['pa-customers-orders'] }, () => {
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        // Create new customer group
        cy.contains('.sw-button__content','Klantgroep aanmaken').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('#sw-field--customerGroup-name').typeAndCheck('VIP Customers');
        cy.get('[name="sw-field--customerGroup-registrationActive"]').check();
        cy.get('.sw-card-view__content .sw-card').eq(1).should('be.visible');
        cy.get('#sw-field--customerGroup-registrationTitle').typeAndCheck('VIP');
        cy.get('.sw-select-selection-list').click();
        cy.get('.sw-select-result-list__content').contains('E2E install test').click();
        cy.contains('.sw-button__content','Opslaan').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan');

        // Register customer
        cy.visit('/VIP');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('#personalSalutation').select('Mr.');
        cy.get('#personalFirstName').typeAndCheckStorefront('Test');
        cy.get('#personalLastName').typeAndCheckStorefront('Tester');
        cy.get('#personalMail').typeAndCheckStorefront('test@tester.com');
        cy.get('#personalPassword').typeAndCheckStorefront('shopware');
        cy.get('#billingAddressAddressStreet').typeAndCheckStorefront('Test street');
        cy.get('#billingAddressAddressZipcode').typeAndCheckStorefront('12345');
        cy.get('#billingAddressAddressCity').typeAndCheckStorefront('Test city');
        cy.get('#billingAddressAddressCountry').select('Netherlands');
        cy.get('.btn.btn-lg.btn-primary').click();
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);
        cy.url().should('include', 'account');
        cy.contains('.alert-content', 'VIP Customers');

        // Accept customer in admin
        cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-customer-list__requested-group-label .sw-label__caption', 'Verzoek VIP Customer');
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            '.sw-context-button__button',
            `.sw-data-grid__row--0`,
        );
        cy.contains('.sw-alert__message', 'VIP Customers').should('be.visible');
        cy.get('.sw-button__content').contains('Akkoord').click();
        cy.awaitAndCheckNotification('De klantgroep is verwijderd');
        cy.get('.smart-bar__back-btn').click();
        cy.contains('[class="sw-data-grid__row sw-data-grid__row--0"] .sw-label--appearance-pill', 'VIP Customer');

        // Verify alert not exist in the storefront
        cy.visit('/account');
        cy.get('.alert-content').should('not.exist');
    });
    it('@package: should register with new commercial customer group', { tags: ['pa-customers-orders'] }, () => {
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
        cy.intercept({
            url: `/account/register`,
            method: 'POST',
        }).as('registerCustomer');

        // Create new commercial customer group
        cy.contains('.sw-button__content','Klantgroep aanmaken').click();
        cy.get('#sw-field--customerGroup-name').typeAndCheck('VIP Commercial');
        cy.get('[name="sw-field--customerGroup-registrationActive"]').check();
        cy.get('.sw-card-view__content .sw-card').eq(1).should('be.visible');
        cy.get('#sw-field--customerGroup-registrationTitle').typeAndCheck('VIP-Commercial');
        cy.get('[name="sw-field--customerGroup-registrationOnlyCompanyRegistration"]').check();
        cy.get('.sw-select-selection-list').click();
        cy.get('.sw-select-result-list__content').contains('E2E install test').click();
        cy.contains('.sw-button__content','Opslaan').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-button-process__content').contains('Opslaan');

        // Register commercial customer
        cy.visit('/VIP-Commercial');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
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
        cy.wait('@registerCustomer').its('response.statusCode').should('equal', 302);
        cy.url().should('include', 'account');
        cy.contains('.alert-content', 'VIP Commercial');

        // Accept commercial customer in admin
        cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-customer-list__requested-group-label .sw-label__caption', 'Verzoek VIP Commercial');
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            '.sw-context-button__button',
            `.sw-data-grid__row--0`,
        );
        cy.contains('.sw-alert__message', 'VIP Commercial').should('be.visible');
        cy.get('.sw-button__content').contains('Akkoord').click();
        cy.awaitAndCheckNotification('De klantgroep is verwijderd');
        cy.get('.smart-bar__back-btn').click();
        cy.contains('[class="sw-data-grid__row sw-data-grid__row--0"] .sw-label--appearance-pill', 'VIP Commercial');

        // Verify alert not exist in the storefront
        cy.visit('/account');
        cy.get('.alert-content').should('not.exist');
    });
});
