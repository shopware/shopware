/// <reference types="Cypress" />

import CustomerPageObject from '../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany'
};
const newAddress = {
    salutation: 'Mr.',
    firstName: 'Harry',
    lastName: 'Potter',
    addresses: [{
        street: 'Ligusterweg 4',
        zipcode: '333333',
        city: 'Little Whinging'
    }],
    country: 'United Kingdom'
};

describe('Customer:  Visual test', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.loginViaApi();
            })
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
            });
    });

    it('@visual: check appearance of basic customer workflow', () => {
        const page = new CustomerPageObject();
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: 'api/customer',
            method: 'post'
        }).as('saveData');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('Customer listing', '.sw-customer-list-grid');

        // Fill in basic data
        cy.get('a[href="#/sw/customer/create"]').click();

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Customer create', '.sw-customer-create');

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
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        const language = Cypress.env('locale') === 'en-GB' ? 'English' : 'Deutsch';
        cy.get('.sw-card-section--secondary').contains(language);

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('Customer detail', '.sw-customer-card');
    });
});
