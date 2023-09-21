/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Promotion v2: Visual tests', () => {
    beforeEach(() => {
        cy.createDefaultFixture('promotion').then(() => {
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
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@visual: check appearance of basic promotion workflow', { tags: ['pa-checkout', 'VUE3'] }, () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion`,
            method: 'POST',
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/promotion/**`,
            method: 'PATCH',
        }).as('patchPromotion');
        cy.intercept({
            url: '/widgets/checkout/info',
            method: 'GET',
        }).as('cartInfo');

        cy.clickMainMenuItem({
            targetPath: '#/sw/promotion/v2/index',
            mainMenuId: 'sw-marketing',
            subMenuId: 'sw-promotion-v2',
        });
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-promotion-v2-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-promotion-v2-empty-state-hero').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Promotion] Listing', '.sw-promotion-v2-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('a[href="#/sw/promotion/v2/create"]').click();
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Create promotion
        cy.get('.sw-promotion-v2-detail').should('be.visible');
        cy.get('input[label="Name"]').typeAndCheck('Funicular prices');
        cy.get('.sw-promotion-v2-detail-base__field-active input').click();

        cy.get('.sw-promotion-v2-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        const save = Cypress.env('locale') === 'en-GB' ? 'Save' : 'Speichern';
        cy.get('.sw-promotion-v2-detail__save-action').contains(save).trigger('mouseout').trigger('mouseleave');
        cy.contains('General settings').click();
        cy.get('.sw-tooltip').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Promotion] Detail', '.sw-promotion-v2-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get(page.elements.smartBarBack).click();
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`,
            'Funicular prices');
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

        cy.get('.sw-promotion-discount-component__type-select select').select('Fixed item price');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Promotion] Detail, discounts', '.sw-promotion-discount-component', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        // Save final promotion
        cy.get('.sw-promotion-v2-detail__save-action').click();

        cy.wait('@patchPromotion')
            .its('response.statusCode').should('equal', 204);

        // Verify Promotion in Storefront
        cy.visit('/');
        cy.get('.product-box').should('be.visible');
        cy.get('.btn-buy').click();

        cy.get('.offcanvas').should('be.visible');
        cy.wait('@cartInfo').its('response.statusCode').should('within', 200, 204);

        cy.get('.loader').should('not.exist');

        cy.changeElementStyling(
            '.header-search',
            'visibility: hidden',
        );
        cy.get('.header-search')
            .should('have.css', 'visibility', 'hidden');
        cy.changeElementStyling(
            '#accountWidget',
            'visibility: hidden',
        );
        cy.get('#accountWidget')
            .should('have.css', 'visibility', 'hidden');

        // Change text of the element to ensure consistent snapshots
        cy.changeElementText('.line-item-delivery-date', 'Delivery period: 01/01/2018 - 03/01/2018');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Promotion] Storefront, checkout off-canvas ', '.offcanvas');
    });
});
