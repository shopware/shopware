// / <reference types="Cypress" />

import ProductPageObject from "../../../../support/pages/module/sw-product.page-object";

const page = new ProductPageObject();

describe('SDK Tests: Location', ()=> {
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

    it('@sdk: update the height of the location iFrame', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.log('Go to specifications tab');

        cy.contains('.sw-tabs-item', 'Specifications')
            .click();

        cy.contains('.sw-card__title', 'Location tests');

        cy.log('Update the iFrame height manually');

        cy.getSDKiFrame('location-index')
            .contains('button', 'Stop auto resizing')
            .click();

        // location / stop auto resizing of iFrame height
        cy.getSDKiFrame('location-index')
            .contains('Auto-Resize: Off');

        cy.getSDKiFrame('location-index')
            .find('input')
            .clear()
            .type('456');

        cy.getSDKiFrame('location-index')
            .contains('button', 'Update height manually')
            .click();

        cy.get(`iframe[src*="location-id=location-index"]`)
            .should('have.attr', 'height', '456px');
    });

    it('@sdk: start auto resizing of the iFrame height', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.log('Go to specifications tab');

        cy.contains('.sw-tabs-item', 'Specifications')
            .click();

        cy.contains('.sw-card__title', 'Location tests');

        cy.log('Update the iFrame height automatically');

        cy.getSDKiFrame('location-index')
            .contains('Auto-Resize: On');

        cy.getSDKiFrame('location-index')
            .find('input')
            .clear()
            .type('700');

        cy.getSDKiFrame('location-index')
            .contains('button', 'Update height using auto resizing')
            .click();

        /**
         * Value is higher because the margin and padding inside the iFrame
         * are also considered in automatic height
         */
        cy.get(`iframe[src*="location-id=location-index"]`)
            .should('have.attr', 'height', '700px');
    });
});
