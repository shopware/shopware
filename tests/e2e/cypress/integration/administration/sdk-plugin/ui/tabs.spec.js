// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

                cy.onlyOnFeature('FEATURE_NEXT_17950');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            });
    });
    it('@sdk: Check tab existence', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        const Page = new ProductPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/calculate-price`,
            method: 'POST'
        }).as('calculatePrice');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            Page.elements.contextMenuButton,
            `${Page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-detail__tab-general')
            .should('exist');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        // Check Example Tab
        cy.get(`a[href*="ui-tabs-product-example-tab"`)
            .click();
        cy.get('.sw-card').contains('Hello in the new tab ');

        cy.getSDKiFrame('ui-modals')
            .should('exist');
    })
})
