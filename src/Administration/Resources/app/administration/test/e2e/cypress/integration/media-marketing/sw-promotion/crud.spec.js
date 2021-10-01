// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('Promotion: Test crud operations', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_13810');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('promotion');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createCustomerFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/index`);
            });
    });

    it('@base @marketing: create, update and read promotion', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveDiscount');

        cy.get('a[href="#/sw/promotion/create"]').click();

        // Create promotion
        cy.get('.sw-promotion-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-sales-channel-select').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-sales-channel-select .sw-select-selection-list__input')
            .type('{esc}');
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Funicular prices');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name a`)
            .click();

        // Add discount
        cy.get(page.elements.loader).should('not.exist');
        cy.get('a[title="Discounts"]').click();
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-button--ghost').should('be.visible');
        cy.contains('.sw-button--ghost', 'Add discount').click();
        cy.wait('@filteredResultCall')
            .its('response.statusCode').should('equal', 200);
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('54');

        cy.get('#sw-field--discount-type').select('Fixed item price');

        // Save final promotion
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@saveDiscount').its('response.statusCode').should('equal', 200);

        // Verify promotion in Administration
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Funicular prices');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--active .is--active`)
            .should('be.visible');

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();
        cy.get('.offcanvas.is-open').should('be.visible');
        cy.get('.cart-item-promotion .cart-item-label').contains('Funicular prices');
        cy.get('.cart-item-promotion .cart-item-price').contains('-€10.00*');
        cy.get('.summary-total').contains('€54.00');

        // Order product with promotion
        cy.get('a[title="Proceed to checkout"]').click();
        cy.get('.login-collapse-toggle').click();
        cy.get('.login-card').should('be.visible');
        cy.get('#loginMail').typeAndCheck('test@example.com');
        cy.get('#loginPassword').typeAndCheck('shopware');
        cy.contains('Login').click();

        // Finish order
        cy.get('.confirm-tos .card-title').contains('Terms and conditions and cancellation policy');
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.confirm-tos .custom-checkbox label').click(1, 1);
        cy.get('.confirm-tos .custom-checkbox label').scrollIntoView();
        cy.get('.cart-item-promotion').contains('Funicular prices');
        cy.get('.cart-item-promotion .cart-item-total-price').contains('-€10.00');
        cy.get('.cart-item-promotion .cart-item-tax-price').contains('-€1.60');
        cy.get('.checkout-aside-summary-value.checkout-aside-summary-total')
            .contains('€54.00');

        cy.get('#confirmFormSubmit').scrollIntoView();
        cy.get('#confirmFormSubmit').click();
        cy.get('.finish-header').contains('Thank you for your order with Demostore!');
    });

    it('@base @marketing: delete promotion', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
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
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
        cy.get('button[title="Refresh"]').click();
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.get(page.elements.emptyState).should('be.visible');
    });
});
