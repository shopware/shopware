// / <reference types="Cypress" />

import CustomerPageObject from '../../../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany',
    company: 'Company',
    department: 'Department'
};

describe('Customer:  Visual test', () => {
    beforeEach(() => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/customer`,
            method: 'POST'
        }).as('getData');

        cy.loginViaApi()
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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic customer workflow', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/customer`,
            method: 'POST'
        }).as('saveData');

        cy.get('.sw-customer-list').should('be.visible');

        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-customer-list__content').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Listing', '.sw-customer-list-grid', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // Fill in basic data
        cy.get('a[href="#/sw/customer/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Take snapshot for visual testing
        cy.contains('.sw-select__selection', 'English');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Create', '.sw-customer-create', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-customer-base-form__account-type-select')
            .typeSingleSelectAndCheck('Commercial', '.sw-customer-base-form__account-type-select');

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
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.icon--regular-checkmark-xs').should('be.visible');
        cy.get('.sw-skeleton__detail-bold').should('not.exist');
        cy.get('.sw-skeleton__detail').should('not.exist');
        cy.get('.icon--regular-checkmark-xs').should('not.exist');

        // Take snapshot for visual testing
        cy.contains('.sw-card-section--secondary', 'English');
        cy.contains('Account').click();
        cy.get('.sw-tooltip').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Detail', '.sw-customer-card', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });

    it('@visual: check appearance of customer address workflow', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();

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
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Detail, address listing', '.sw-customer-detail-addresses', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get('.sw-customer-detail__tab-addresses').click();
        cy.get('.sw-customer-detail-addresses__add-address-action').click();

        cy.get('.sw-modal').should('be.visible');

        // Take snapshot for visual testing
        cy.handleModalSnapshot('Address');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Detail, address modal', '#sw-field--address-company', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });

    it('@visual: check appearance of customer edit workflow', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains(`${page.elements.customerMetaData}-customer-name`,
            `Mr. ${customer.firstName} ${customer.lastName}`);

        // Open and edit existing customer

        cy.contains('.sw-button', 'Edit').click();
        cy.url().should('contain', '?edit=true');
        cy.get('.sw-loader-element').should('not.exist');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Customer] Detail, edit view', '#sw-field--customer-title', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
