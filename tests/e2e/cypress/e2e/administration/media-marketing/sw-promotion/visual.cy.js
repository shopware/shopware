// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('Promotion: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_13810');
    });

    beforeEach(() => {
        cy.loginViaApi()
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
                return cy.setShippingMethodInSalesChannel('Standard');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/promotion/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic promotion workflow', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'PATCH'
        }).as('patchPromotion');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.takeSnapshot('[Promotion] listing', '.sw-promotion-list');

        cy.get('a[href="#/sw/promotion/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Create promotion
        cy.get('.sw-promotion-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').click();
        cy.get('.sw-promotion-sales-channel-select').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-promotion-sales-channel-select .sw-select-selection-list__input')
            .type('{esc}');
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Promotion] detail', '.sw-promotion-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Funicular prices');
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

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Promotion] detail - discounts', '.sw-promotion-discount-component', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // Save final promotion
        cy.get('.sw-promotion-detail__save-action').click();
        cy.wait('@patchPromotion')
            .its('response.statusCode').should('equal', 204);

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();

        // Take snapshot for visual testing
        cy.takeSnapshot('[Promotion] In Storefront checkout', '.offcanvas');
    });
});
