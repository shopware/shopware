// / <reference types="Cypress" />

import ProductPageObject from "../../../../support/pages/module/sw-product.page-object";

const page = new ProductPageObject();

describe('SDK Tests: Component section', ()=> {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
        })
            .then(() => {
                cy.log('Open example product');

                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');

                cy.get('.smart-bar__content')
                    .should('be.visible');
                cy.contains('.smart-bar__content', 'Products');

                cy.get('.sw-loader').should('not.exist');
                cy.get('.sw-skeleton').should('not.exist');

                cy.clickContextMenuItem(
                    '.sw-entity-listing__context-menu-edit-action',
                    page.elements.contextMenuButton,
                    `${page.elements.dataGridRow}--0`,
                );

                cy.contains('.smart-bar__content', 'Product name');

                cy.get('.sw-loader').should('not.exist');
                cy.get('.sw-skeleton').should('not.exist');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            });
    });

    it('@sdk: add a component section', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.log('Go to specifications tab');

        cy.contains('.sw-tabs-item', 'Specifications')
            .click();

        cy.contains('.sw-card__title', 'Location tests');
        cy.contains('.sw-card__subtitle', 'Testing if the location methods work correctly');

        cy.getSDKiFrame('location-index')
            .should('be.visible');
    });

    it('@sdk: add a component section with tabs', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.log('Go to specifications tab');

        cy.contains('.sw-tabs-item', 'Specifications')
            .click();

        cy.contains('.sw-card__title', 'Card tabs tests');
        cy.contains('.sw-card__subtitle', 'Testing if the the card tabs work correctly');

        cy.contains('.sw-tabs-item', 'Tab 1');
        cy.getSDKiFrame('card-tab-1')
            .should('be.visible');

        // Switch the tab and check tab content
        cy.contains('.sw-tabs-item', 'Tab 2').click();
        cy.getSDKiFrame('card-tab-2')
            .should('be.visible');
    });
});
