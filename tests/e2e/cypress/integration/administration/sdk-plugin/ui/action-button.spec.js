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
    it('@sdk: action button', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        const Page = new ProductPageObject();

        cy.contains('.smart-bar__content', 'Products')
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            Page.elements.contextMenuButton,
            `${Page.elements.dataGridRow}--0`
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
            .should('be.visible')

        cy.get('.sw-context-menu__content')
            .contains('Test action')
            .click();

        cy.get('.sw-alert__title')
            .contains('Action button click')

        cy.get('.sw-alert__message')
            .contains('The action button in the product detail page was clicked')
    })
})
