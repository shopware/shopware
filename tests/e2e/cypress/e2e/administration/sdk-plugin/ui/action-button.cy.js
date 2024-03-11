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
    it('@sdk: action button', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        const Page = new ProductPageObject();

        cy.contains('.smart-bar__content', 'Products');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            Page.elements.contextMenuButton,
            `${Page.elements.dataGridRow}--0`,
        );

        cy.get('.sw-product-detail__tab-general')
            .should('be.visible');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-app-actions')
            .click();

        cy.get('.sw-context-menu__content')
            .should('be.visible');

        cy.contains('.sw-context-menu__content .sw-app-action-button', 'Test action')
            .click();

        cy.contains('.sw-alert__title', 'Action button click');

        cy.contains('.sw-alert__message',
            'The action button in the product detail page was clicked');
    });
});
