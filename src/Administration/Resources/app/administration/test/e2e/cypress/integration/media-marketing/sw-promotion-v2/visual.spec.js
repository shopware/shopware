// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Promotion v2: Visual tests', () => {
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
                return cy.setShippingMethodInSalesChannel('Standard');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of basic promotion workflow', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'patch'
        }).as('patchPromotion');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/promotion`,
            method: 'post'
        }).as('getData');
        cy.route({
            url: '/widgets/checkout/info',
            method: 'get'
        }).as('cartInfo');

        cy.clickMainMenuItem({
            targetPath: '#/sw/promotion/v2/index',
            mainMenuId: 'sw-marketing',
            subMenuId: 'sw-promotion-v2'
        });
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.sw-promotion-v2-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-promotion-v2-empty-state-hero').should('not.exist');
        cy.takeSnapshot('[Promotion] Listing', '.sw-promotion-v2-list');

        cy.get('a[href="#/sw/promotion/v2/create"]').click();

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('#sw-field--promotion-name').typeAndCheck('Funicular prices');
        cy.get('input[name="sw-field--promotion-active"]').click();

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.contains('General settings').click();
        cy.get('.sw-tooltip').should('not.exist');
        cy.takeSnapshot('[Promotion] Detail', '.sw-promotion-v2-detail');

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
        cy.get(page.elements.loader).should('not.exist');

        cy.get('.sw-promotion-discount-component').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value').should('be.visible');
        cy.get('.sw-promotion-discount-component__discount-value input')
            .clear()
            .type('54');

        cy.get('#sw-field--discount-type').select('Fixed item price');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Promotion] Detail, discounts', '.sw-promotion-discount-component');

        // Save final promotion
        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@patchPromotion').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();

        cy.get('.offcanvas').should('be.visible');
        cy.wait('@cartInfo').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });
        cy.get('.loader').should('not.exist');

        cy.changeElementStyling(
            '.header-search',
            'visibility: hidden'
        );
        cy.get('.header-search')
            .should('have.css', 'visibility', 'hidden');
        cy.changeElementStyling(
            '#accountWidget',
            'visibility: hidden'
        );
        cy.get('#accountWidget')
            .should('have.css', 'visibility', 'hidden');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Promotion] Storefront, checkout off-canvas ', '.offcanvas.is-open');
    });
});
