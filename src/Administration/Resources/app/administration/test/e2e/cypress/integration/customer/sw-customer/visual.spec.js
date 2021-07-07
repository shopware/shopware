// / <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany',
    company: 'Company',
    department: 'Department'
};

describe('Customer:  Visual test', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                return cy.createCustomerFixture();
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
            url: `${Cypress.env('apiPath')}/customer`,
            method: 'post'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'post'
        }).as('getData');

        cy.get('.sw-customer-list').should('be.visible');

        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-customer-list__content').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Customer] Listing', '.sw-customer-list-grid');

        // Fill in basic data
        cy.get('a[href="#/sw/customer/create"]').click();

        // Take snapshot for visual testing
        cy.get('.sw-select__selection').contains('English');
        cy.takeSnapshot('[Customer] Create', '.sw-customer-create');

        cy.get('.sw-customer-base-form__salutation-select')
            .typeSingleSelectAndCheck('Mr.', '.sw-customer-base-form__salutation-select');

        cy.get('input[name=sw-field--customer-firstName]').type(customer.firstName);
        cy.get('input[name=sw-field--customer-lastName]').type(customer.lastName);
        cy.get(page.elements.customerMailInput).type('tester@example.com');

        cy.get('.sw-customer-base-form__customer-group-select')
            .typeSingleSelectAndCheck('Standard customer group', '.sw-customer-base-form__customer-group-select');

        cy.get('.sw-customer-base-form__sales-channel-select')
            .typeSingleSelectAndCheck('Storefront', '.sw-customer-base-form__sales-channel-select');

        cy.get('.sw-customer-base-form__payment-method-select')
            .typeSingleSelectAndCheck('Invoice', '.sw-customer-base-form__payment-method-select');

        cy.get('#sw-field--customer-password').type('shopware');

        // Fill in address and save
        page.createBasicAddress(customer);
        cy.get(page.elements.customerSaveAction).click();

        // Verify new customer in detail
        cy.wait('@saveData').then((xhr) => {
            cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
            expect(xhr).to.have.property('status', 204);
            cy.get('.icon--small-default-checkmark-line-medium').should('not.exist');
        });

        // Take snapshot for visual testing
        cy.get('.sw-card-section--secondary').contains('English');
        cy.contains('Account').click();
        cy.get('.sw-tooltip').should('not.exist');
        cy.takeSnapshot('[Customer] Detail', '.sw-customer-card');
    });

    it('@visual: check appearance of customer address workflow', () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/country`,
            method: 'post'
        }).as('getCountries');

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.customerMetaData}-customer-name`)
            .contains(`Mr. ${customer.firstName} ${customer.lastName}`);

        // Open and add new address
        cy.get('.sw-customer-detail__tab-addresses').click();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Customer] Detail, address listing', '.sw-customer-detail-addresses');

        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get('.sw-customer-detail-addresses__add-address-action').click();

        cy.wait('@getCountries').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Take snapshot for visual testing
        cy.handleModalSnapshot('Address');
        cy.takeSnapshot('[Customer] Detail, address modal', '#sw-field--address-company');
    });

    it('@visual: check appearance of customer edit workflow', () => {
        const page = new CustomerPageObject();

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.customerMetaData}-customer-name`)
            .contains(`Mr. ${customer.firstName} ${customer.lastName}`);

        // Open and edit existing customer

        cy.contains('.sw-button', 'Edit').click();
        cy.url().should('contain', '?edit=true');
        cy.get('.sw-loader__element').should('not.exist');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Customer] Detail, edit view', '#sw-field--customer-title');
    });
});
