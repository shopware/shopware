// / <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

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

describe('Customer: Edit customer\'s addresses', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                return cy.fixture('customer');
            })
            .then((result) => {
                customer = result;
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
            });
    });

    it('@base @customer: add new billing address', () => {
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
        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get('.sw-customer-detail-addresses__add-address-action').click();
        page.createBasicAddress(newAddress);
        cy.get(`${page.elements.modal} ${page.elements.primaryButton}`).click();

        // Verify updated customer
        cy.contains(`Mr. ${customer.firstName} ${customer.lastName}`);
    });

    it('@base @customer: remove address', () => {
        const page = new CustomerPageObject();

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.customerMetaData}-customer-name`)
            .contains(`Mr. ${customer.firstName} ${customer.lastName}`);

        // Remove address
        cy.get('.sw-customer-detail__tab-addresses').click();
        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get(`${page.elements.dataGridRow}--1`).then(($btn) => {
            if ($btn.text().includes(customer.lastName)) {
                cy.get('.sw-data-grid__cell--2').click();
                cy.get(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0:checked`)
                    .should('be.visible');
            }
        });

        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`
        );
        cy.get(`${page.elements.modal} p`).contains(
            'Are you sure you want to delete this address?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.primaryButton}`).click();

        // Verify updated customer
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
    });


    it('@customer: swap default billing and shipping address', () => {
        const page = new CustomerPageObject();

        cy.get('.sw-customer-list__content').should('be.visible');

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Open and swap default in addresses
        cy.get('.sw-customer-detail__tab-addresses').click();
        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get(`${page.elements.dataGridRow}--1`).then(($btn) => {
            if ($btn.text().includes(customer.lastName)) {
                cy.get('.sw-data-grid__cell--2').click();
                cy.get('.icon--small-arrow-small-up').should('be.visible');
                cy.get(`${page.elements.dataGridRow}--0 #defaultShippingAddress-0:checked`)
                    .should('be.visible');
            }
        });
        cy.get(`${page.elements.dataGridRow}--0`).contains(customer.lastName);
        cy.get('.icon--default-shopping-cart').should('be.visible');
        cy.get(`${page.elements.dataGridRow}--1 #defaultShippingAddress-0`)
            .should('be.visible')
            .click();
        cy.get(`${page.elements.dataGridRow}--1 #defaultShippingAddress-0:checked`).should('be.visible');

        cy.get(`${page.elements.dataGridRow}--1 #defaultBillingAddress-0`)
            .should('be.visible')
            .click();
        cy.get(`${page.elements.dataGridRow}--1 #defaultBillingAddress-0:checked`).should('be.visible');
    });
});
