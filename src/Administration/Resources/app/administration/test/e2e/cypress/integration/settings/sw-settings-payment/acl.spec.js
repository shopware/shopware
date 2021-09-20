// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Payment: Test ACL privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('payment-method');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/index`);
            });
    });

    it('@settings: has no access to payment module', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'property',
                role: 'viewer'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        });

        // open settings-payment without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.get('h1').contains('Access denied');
        cy.get('.sw-settings-payment-list').should('not.exist');
    });

    it('@settings: can view payment', () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        });

        // open settings-payment
        cy.contains('.sw-data-grid__cell-value', 'CredStick').click();

        // check settings-payment values
        cy.get('.sw-payment-detail__save-action').should('be.disabled');
    });

    it('@settings: can edit payment', () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method/*`,
            method: 'PATCH'
        }).as('savePayment');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer'
            }, {
                key: 'payment',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        });

        // open payment method
        cy.contains('.sw-data-grid__cell-value', 'CredStick').click();
        cy.get('#sw-field--paymentMethod-description').type('My description');
        cy.get('#sw-field--paymentMethod-position').clearTypeAndCheck('0');

        // Verify updated payment method
        cy.get('.sw-payment-detail__save-action').should('not.be.disabled');
        cy.get('.sw-payment-detail__save-action').click();
        cy.wait('@savePayment').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--description`)
            .contains('My description');
    });

    it('@settings: can create payment', () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method`,
            method: 'POST'
        }).as('saveData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer'
            }, {
                key: 'payment',
                role: 'editor'
            }, {
                key: 'payment',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/create`);
        });

        // Add payment method
        cy.get('#sw-field--paymentMethod-name').typeAndCheck('1 Coleur');
        cy.get('.sw-payment-detail__save-action').should('not.be.disabled');
        cy.get('.sw-payment-detail__save-action').click();

        // Verify payment method in listing
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get(page.elements.smartBarBack).click();
        cy.contains('.sw-data-grid__row', '1 Coleur');
    });

    it('@settings: can delete settings-payment', () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method/*`,
            method: 'delete'
        }).as('deleteData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer'
            }, {
                key: 'payment',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/index`);
        });

        // open settings-payment
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-settings-payment-list__confirm-delete-text`)
            .contains('Are you sure you want to delete the payment method');
    });
});
