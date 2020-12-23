/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Promotion v2: Test crud operations', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_12016');
    });

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('promotion');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/v2/index`);
        });
    });

    it('@base @marketing: create, update and read promotion', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'post'
        }).as('saveData');

        cy.waitFor('.sw-promotion-v2-list__button-add-promotion');
        cy.get('.sw-promotion-v2-list__button-add-promotion').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.location('hash').should((hash) => {
            expect(hash).to.contain('#/sw/promotion/v2/detail/')
        });

        cy.get('.sw-loader').should('not.be.visible');

        cy.get('#sw-field--promotion-validFrom + input')
            .click()
            .type('2222-01-01{enter}');

        cy.get('#sw-field--promotion-validUntil + input')
            .click()
            .type('2222-02-02{enter}');

        cy.get('#sw-field--promotion-maxRedemptionsGlobal').type('1');

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.get('.sw-loader').should('not.be.visible');

        // Verify promotion on detail page
        cy.get('#sw-field--promotion-name').should('have.value', 'Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').should('be.checked');
        cy.get('#sw-field--promotion-validFrom + input').should('contain.value', '2222-01-01');
        cy.get('#sw-field--promotion-validUntil + input').should('contain.value','2222-02-02');
        cy.get('#sw-field--promotion-maxRedemptionsGlobal').should('have.value','1');
        cy.get('#sw-field--promotion-maxRedemptionsPerCustomer')
            .should('be.empty')
            .should('have.attr', 'placeholder', 'Unlimited');

        // Verify promotion in listing
        cy.get('.sw-promotion-v2-detail__cancel-action').click();

        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').contains('Funicular prices');
        cy.get('.sw-data-grid__cell--active > .sw-data-grid__cell-content > span').should('have.class', 'is--active');
        cy.get('.sw-data-grid__cell--validFrom > .sw-data-grid__cell-content').contains('01/01/22');
        cy.get('.sw-data-grid__cell--validUntil > .sw-data-grid__cell-content').contains('02/02/22');
    });

    it('@base @marketing: delete promotion', () => {
        const page = new ProductPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion/*`,
            method: 'delete'
        }).as('deleteData');

        // Delete product
        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        cy.get(`${page.elements.modal} .sw-listing__confirm-delete-text`).contains(
            'Are you sure you want to delete this item?'
        );
        cy.get(`${page.elements.modal}__footer ${page.elements.dangerButton}`).click();

        // Verify updated product
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-sidebar-navigation-item[title="Refresh"]').click();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
