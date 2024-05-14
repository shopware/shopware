// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');

            cy.getSDKiFrame('sw-main-hidden')
                .should('exist');
        });
    });
    it('@sdk: Check tab existence', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        const Page = new ProductPageObject();

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            Page.elements.contextMenuButton,
            `${Page.elements.dataGridRow}--0`,
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
        cy.contains('.sw-card', 'Hello in the new tab ');

        cy.getSDKiFrame('ui-modals')
            .should('exist');

        // TODO: add reload and check if tab still exists with content
    });
});
