// / <reference types="Cypress" />

import CustomerPageObject from '../../../../support/pages/module/sw-customer.page-object';

describe('Customer:  Edit in various ways', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@customer: navigate to create order page', { tags: ['pa-customers-orders'] }, () => {
        const page = new CustomerPageObject();

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--firstName`).contains('Eroni, Pep');
        // Request we want to wait for later
        cy.clickContextMenuItem(
            '.sw-customer-list__edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-customer-detail__tab-order').click();
        cy.get('.sw-empty-state .sw-customer-detail-order__add-order-action').click();

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-user-card').should('be.visible');
            cy.get('.sw-order-create-details-header .sw-entity-single-select').contains('Pep Eroni');
        });

        cy.onlyOnFeature('FEATURE_NEXT_7530', () => {
            cy.get('.sw-order-create-initial-modal').should('be.visible');
            cy.get('.sw-order-customer-grid').should('be.visible');

            cy.get('.sw-loader').should('not.exist');
            cy.get('.sw-data-grid__row--0 .sw-data-grid__cell--firstName').contains('Pep Eroni');
        });
    });
});
