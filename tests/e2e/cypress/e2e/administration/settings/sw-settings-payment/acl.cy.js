// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Payment: Test ACL privileges', () => {
    beforeEach(() => {
        cy.createDefaultFixture('payment-method')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/payment/overview`);
            });
    });

    it('@settings: has no access to payment module', { tags: ['pa-checkout', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'property',
                role: 'viewer',
            },
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

    it('@settings: can view payment but is not able to edit or activate payment ', { tags: ['pa-checkout', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer',
            },
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

    it('@settings: can edit payment', { tags: ['pa-checkout', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method/*`,
            method: 'PATCH',
        }).as('savePayment');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer',
            }, {
                key: 'payment',
                role: 'editor',
            },
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
        cy.get('.sw-settings-payment-detail-delete').should('not.exist');

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

    it('@settings: can create payment', { tags: ['pa-checkout', 'VUE3'] }, () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method`,
            method: 'POST',
        }).as('saveData');

        const page = new ProductPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'viewer',
            }, {
                key: 'payment',
                role: 'editor',
            }, {
                key: 'payment',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/settings/payment/create`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        // Add payment method
        cy.get('#sw-field--paymentMethod-name').typeAndCheck('1 Coleur');
        cy.get('#sw-field--paymentMethod-technicalName').typeAndCheck('payment-coleur');
        cy.get('.sw-payment-detail__save-action').should('not.be.disabled');
        cy.get('.sw-payment-detail__save-action').click();

        // Verify payment method in listing
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();

        cy.contains('.sw-card__title', '1 Coleur');
    });

    it('@settings: can delete payment', { tags: ['pa-checkout', 'VUE3'] }, () => {

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/payment-method/*`,
            method: 'PATCH',
        }).as('savePayment');

        cy.loginAsUserWithPermissions([
            {
                key: 'payment',
                role: 'editor',
            }, {
                key: 'payment',
                role: 'deleter',
            },
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

        cy.get('.sw-settings-payment-detail-delete').should('be.visible');
    });
});
