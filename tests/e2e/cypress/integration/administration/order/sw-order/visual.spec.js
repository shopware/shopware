// / <reference types="Cypress" />

import OrderPageObject from '../../../../support/pages/module/sw-order.page-object';

describe('Order: Visual tests', () => {
    beforeEach(() => {
        cy.setShippingMethodInSalesChannel('Standard').then(() => {
            return cy.createProductFixture();
        }).then(() => {
            return cy.searchViaAdminApi({
                endpoint: 'product',
                data: {
                    field: 'name',
                    value: 'Product name'
                }
            });
        })
        .then((result) => {
            return cy.createGuestOrder(result.id);
        }).then(() => {
            cy.loginViaApi()
        }).then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now, ['Date']);
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/order/index`);
        });
    });

    it('@visual: check appearance of basic order workflow', () => {
        const page = new OrderPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('getData');

        cy.get('.sw-data-grid__cell--orderNumber').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--orderDateTime', 'color: #fff');
        cy.get('.sw-data-grid__cell--orderDateTime')
            .should('have.css', 'color', 'rgb(255, 255, 255)');

        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Listing', '.sw-order-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.contains(`${page.elements.dataGridRow}--0`, 'Mustermann, Max');

        cy.get(`${page.elements.dataGridRow}--0 ${page.elements.contextMenuButton}`).click();
        cy.get('.sw-context-menu')
            .should('be.visible');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Listing, Context menu open', '.sw-page', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-order-list__order-view-action').click();

        cy.skipOnFeature('FEATURE_NEXT_7530', () => {

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('.sw-order-user-card__metadata-item', '01 Jan 2018, 00:00');

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('.sw-order-state-history-card__payment-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('.sw-order-state-history-card__delivery-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('.sw-order-state-history-card__order-state .sw-order-state-card__date', '01 Jan 2018, 00:00');

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('div.sw-card.sw-card--grid.has--header.has--title.sw-order-user-card > div.sw-card__content > div > div.sw-card-section.sw-card-section--secondary.sw-card-section--slim > div > dl:nth-child(2) > dd:nth-child(4)', '01 Jan 2018, 00:00');

            // Change text of the element to ensure consistent snapshots
            cy.changeElementText('div.sw-card.has--header.has--title.sw-order-delivery-metadata > div.sw-card__content > div > dl:nth-child(1) > dd:nth-child(4)', '01 Jan 2018, 00:00');

        });
        // Take snapshot for visual testing
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Detail', '.sw-order-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });

    it('@visual: check appearance of order creation workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/order`,
            method: 'POST'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/order/index',
            mainMenuId: 'sw-order',
            subMenuId: 'sw-order-index'
        });
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);
        cy.get('.sw-order-list').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-skeleton__listing').should('not.exist');
        cy.get('.sw-order-list').should('be.visible');
        cy.contains('.sw-button', 'Add order').click();

        cy.get('.sw-loader').should('not.exist');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Order] Create', '.sw-order-create', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
