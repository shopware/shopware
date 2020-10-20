// / <reference types="Cypress" />

import CustomerPageObject from '../../../support/pages/module/sw-customer.page-object';

let customer = {
    salutation: 'Mr.',
    country: 'Germany'
};

describe('Customer: Test ACL privileges', () => {
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

    it('@customer: has no access to property module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'product',
                role: 'viewer'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
        });

        // open property without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-property-list').should('not.exist');

        // see menu without property menu item
        cy.get('.sw-admin-menu__item--sw-customer').should('not.exist');
    });

    it('@customer: can view customer', () => {
        const page = new CustomerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
        });

        // open customer
        cy.get(`${page.elements.dataGridRow}--0`)
            .get('.sw-data-grid__cell--firstName')
            .contains('Eroni')
            .click();

        // check customer values
        cy.get('.sw-customer-detail__open-edit-mode-action').should('be.disabled');
    });

    it('@customer: can edit customer', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/customer/*',
            method: 'patch'
        }).as('saveCustomer');

        const page = new CustomerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer',
                role: 'viewer'
            }, {
                key: 'customer',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
        });

        // open customer
        cy.get(`${page.elements.dataGridRow}--0`)
            .get('.sw-data-grid__cell--firstName')
            .contains('Eroni')
            .click();

        // Verify updated product
        cy.get('.sw-customer-detail__open-edit-mode-action').should('not.be.disabled');
        cy.get('.sw-customer-detail__open-edit-mode-action').click();
        cy.get('#sw-field--customer-lastName').clear();
        cy.get('#sw-field--customer-lastName').type('Rika');
        cy.get('.sw-customer-detail__save-action').click();
        cy.wait('@saveCustomer').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--firstName`)
            .contains('Rika');
    });

    it('@customer: can create customer', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/customer`,
            method: 'post'
        }).as('saveData');

        const page = new CustomerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer',
                role: 'viewer'
            }, {
                key: 'customer',
                role: 'editor'
            }, {
                key: 'customer',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/customer/create`);
        });

        // Add customer group

        cy.get('.sw-customer-base-form__salutation-select')
            .typeSingleSelectAndCheck('Mr.', '.sw-customer-base-form__salutation-select');

        cy.get('input[name=sw-field--customer-firstName]').type(customer.firstName);
        cy.get('input[name=sw-field--customer-lastName]').type(customer.lastName);
        cy.get(page.elements.customerMailInput).type('tester@example.com');

        cy.get('.sw-customer-base-form__customer-group-select')
            .typeSingleSelectAndCheck(
                'Standard customer group',
                '.sw-customer-base-form__customer-group-select'
            );

        cy.get('.sw-customer-base-form__sales-channel-select')
            .typeSingleSelectAndCheck('Storefront', '.sw-customer-base-form__sales-channel-select');

        cy.get('.sw-customer-base-form__payment-method-select')
            .typeSingleSelectAndCheck('Invoice', '.sw-customer-base-form__payment-method-select');

        cy.get('#sw-field--customer-password').type('shopware');

        // Fill in address and save
        page.createBasicAddress(customer);
        cy.get(page.elements.customerSaveAction).click();

        // Verify customer in listing
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });

    it('@customer: can delete customer', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/customer/*',
            method: 'delete'
        }).as('deleteData');

        const page = new CustomerPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'customer',
                role: 'viewer'
            }, {
                key: 'customer',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/customer/index`);
        });

        // open customer
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-customer-list__confirm-delete-text`)
            .contains('Are you sure you want to delete the customer "Pep Eroni"?');

        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        // Verify new options in listing
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get(page.elements.modal).should('not.exist');
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
