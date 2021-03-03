// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Mode advanced settings at product detail on General tab', () => {
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_12429');
    });

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('tag');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@catalogue: should be not show the promotion switch when General settings was unchecked', () => {
        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(1).click();

        cy.get('.sw-product-basic-form__promotion-switch').should('not.be.visible');
    });

    it('@catalogue: should be not show the labelling card when Labelling settings was unchecked', () => {
        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(5).click();

        cy.get('.sw-product-detail-base__labelling-card').should('not.be.visible');
    });
});
