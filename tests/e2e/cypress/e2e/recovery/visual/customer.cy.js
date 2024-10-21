/// <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany',
};

describe('Customer:  Visual test', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                return cy.fixture('customer');
            })
            .then((result) => {
                customer = Cypress._.merge(customer, result);

                return cy.fixture('customer-address');
            })
            .then((result) => {
                customer = Cypress._.merge(customer, result);
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic customer workflow', { tags: ['pa-services-settings'] }, () => {
        const page = new CustomerPageObject();
        // Request we want to wait for later
        cy.intercept({
            url: 'api/customer',
            method: 'post',
        }).as('saveData');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Customer listing`, '.sw-customer-list-grid');

        // Fill in basic data
        cy.get('a[href="#/sw/customer/create"]').click();

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Customer create`, '.sw-customer-create');

        const accountType = Cypress.env('locale') === 'en-GB' ? 'Commercial' : 'Gewerblich';
        cy.get('.sw-customer-base-form__account-type-select')
            .typeSingleSelectAndCheck(accountType, '.sw-customer-base-form__account-type-select');

        const salutation = Cypress.env('locale') === 'en-GB' ? 'Mr' : 'Herr';
        cy.get('.sw-customer-base-form__salutation-select')
            .typeSingleSelectAndCheck(salutation, '.sw-customer-base-form__salutation-select');

        cy.get('input[name=sw-field--customer-firstName]').type(customer.firstName);
        cy.get('input[name=sw-field--customer-lastName]').type(customer.lastName);
        cy.get(page.elements.customerMailInput).type('tester@example.com');

        const customerGroup = Cypress.env('locale') === 'en-GB' ? 'Standard customer group' : 'Standard-Kundengruppe';
        cy.get('.sw-customer-base-form__customer-group-select')
            .typeSingleSelectAndCheck(customerGroup, '.sw-customer-base-form__customer-group-select');

        const saleschannel = Cypress.env('testDataUsage') ? 'Footwear' : 'E2E install test';
        cy.get('.sw-customer-base-form__sales-channel-select')
            .typeSingleSelectAndCheck(saleschannel, '.sw-customer-base-form__sales-channel-select');

        const paymentMethod = Cypress.env('locale') === 'en-GB' ? 'Invoice' : 'Rechnung';
        cy.get('.sw-customer-base-form__payment-method-select')
            .typeSingleSelectAndCheck(paymentMethod, '.sw-customer-base-form__payment-method-select');

        cy.get('#sw-field--customer-password').type('shopware');

        // Fill in address and save
        customer.salutation = Cypress.env('locale') === 'en-GB' ? 'Mr' : 'Herr';
        customer.country = Cypress.env('locale') === 'en-GB' ? 'Germany' : 'Deutschland';
        page.createBasicAddress(customer);
        cy.get(page.elements.customerSaveAction).click();

        // Verify new customer in detail
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        const language = Cypress.env('locale') === 'en-GB' ? 'English' : 'Deutsch';
        cy.get('.sw-card-section--secondary').contains(language);

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Customer detail`, '.sw-customer-card', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--firstName .sw-data-grid__cell-content')
            .should('contain',`${customer.lastName}, ${customer.firstName}`);

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Customer listing after the customer created`, null, null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
