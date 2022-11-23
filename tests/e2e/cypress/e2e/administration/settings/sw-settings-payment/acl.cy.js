// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Payment: Test ACL privileges', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('payment-method');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
            });
    });

    it('@settings: has no access to payment module', { tags: ['pa-checkout'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'property',
                role: 'viewer'
            }
        ]).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open settings-payment without permissions
        cy.get('.sw-privilege-error__access-denied-image').should('be.visible');
        cy.contains('h1', 'Access denied');
        cy.get('.sw-settings-payment-list').should('not.exist');
    });

    it('@settings: can view payment but is not able to edit or activate payment ', { tags: ['pa-checkout'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open settings-payment
        cy.get('.sw-card__title')
            .contains('CredStick')
            .closest('.sw-card')
            .contains('.sw-internal-link.sw-internal-link--disabled', 'Edit detail');

        // check settings-payment values
        cy.get('.sw-card__title')
            .contains('CredStick')
            .closest('.sw-card')
            .get('input[type=checkbox]')
            .should('be.disabled');
    });

    it('@settings: can edit payment', { tags: ['pa-checkout'] }, () => {
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
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open settings-payment
        cy.get('.sw-card__title')
            .contains('CredStick')
            .closest('.sw-card')
            .contains('Edit detail')
            .click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-settings-payment-detail__field-name').should('be.visible');
        cy.get('#sw-field--paymentMethod-description').type('My description');
        cy.get('#sw-field--paymentMethod-position').clearTypeAndCheck('0');

        // Verify updated payment method
        cy.get('.sw-payment-detail__save-action').should('not.be.disabled');
        cy.get('.sw-payment-detail__save-action').click();
        cy.wait('@savePayment').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-card__title')
            .contains('CredStick')
            .closest('.sw-card')
            .contains('My description');
    });

    it('@settings: can create payment', { tags: ['pa-checkout', 'quarantined'] }, () => {
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
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Add payment method
        cy.get('#sw-field--paymentMethod-name').typeAndCheck('1 Coleur');
        cy.get('.sw-payment-detail__save-action').should('not.be.disabled');
        cy.get('.sw-payment-detail__save-action').click();

        // Verify payment method in listing
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();

        cy.get('.sw-card__title')
            .contains('1 Coleur');
    });

    it('@settings: can delete settings-payment', { tags: ['pa-checkout'] }, () => {
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
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // open settings-payment
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.contains(`${page.elements.modal} .sw-settings-payment-list__confirm-delete-text`,
            'Are you sure you want to delete the payment method');
    });
});
