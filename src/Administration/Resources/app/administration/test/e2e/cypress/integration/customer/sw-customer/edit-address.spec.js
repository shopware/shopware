/// <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany',
    company: 'Test Company',
    department: 'Test Department',
    vatId: 'TEST-VAT-ID'
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
    country: 'United Kingdom',
    company: 'Test Company',
    department: 'Test Department'
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

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `/api/v*/customer-address/*`,
            method: 'delete'
        }).as('deleteAddress');

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
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        cy.wait('@deleteAddress').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
        })
    });

    it('@base @customer: go to edit mode, save and edit again and then remove address', () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `/api/v*/customer-address/*`,
            method: 'delete'
        }).as('deleteAddress');

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.customerMetaData}-customer-name`)
            .contains(`Mr. ${customer.firstName} ${customer.lastName}`);

        // Go to addresses tab
        cy.get('.sw-customer-detail__tab-addresses').click();

        // Activate edit mode
        cy.get('.sw-customer-detail__open-edit-mode-action').click();

        // Save
        cy.get('.sw-customer-detail__save-action').click();

        // Activate edit mode again

        // Remove address
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
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        cy.wait('@deleteAddress').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
        })
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

    it('@base @customer: duplicate address', () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `/api/v*/search/customer/**/addresses`,
            method: 'post'
        }).as('searchAddresses');
        cy.route({
            url: '/api/v*/_action/clone/customer-address/**',
            method: 'post'
        }).as('cloneAddress');

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
            '.sw-context-menu-item',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`,
            'Duplicate'
        );

        cy.wait(['@cloneAddress', '@searchAddresses']).then((xhrRequests) => {
            xhrRequests.forEach(xhr => expect(xhr).to.have.property('status', 200));
            cy.get(`${page.elements.dataGridRow}--2`).should('be.visible');
        });
    });

    it('@base @customer: search addresses', () => {
        const page = new CustomerPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `/api/v*/search/customer/**/addresses`,
            method: 'post'
        }).as('searchAddresses');

        // Open customer
        cy.clickContextMenuItem(
            '.sw-customer-list__view-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.customerMetaData}-customer-name`)
            .contains(`Mr. ${customer.firstName} ${customer.lastName}`);

        // Go to addresses tab
        cy.get('.sw-customer-detail__tab-addresses').click();

        cy.get('.sw-simple-search-field input').type('Lemon');

        // Verify search addresses
        cy.wait('@searchAddresses').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get(`${page.elements.dataGridRow}--0`).should('be.visible').contains('Lemon');
        });
    });
});
