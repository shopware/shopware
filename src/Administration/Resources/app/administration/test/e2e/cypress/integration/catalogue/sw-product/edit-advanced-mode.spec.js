// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Mode advanced settings at product detail on General tab', () => {
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
        cy.onlyOnFeature('FEATURE_NEXT_12429');

        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(0).click();

        cy.get('.sw-product-basic-form__promotion-switch').should('not.be.visible');
    });

    it('@catalogue: should be not show the labelling card when Labelling settings was unchecked', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12429');

        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(4).click();

        cy.get('.sw-product-detail-base__labelling-card').should('not.be.visible');
    });
});

describe('Product: Mode advanced settings at product detail on Specifications tab', () => {
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

    it('@catalogue: should be not show the Properties card when Properties settings was unchecked', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12429');

        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();

        cy.get('.sw-product-detail-properties').should('be.visible');

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(1).click();

        cy.get('.sw-product-detail-properties').should('not.be.visible');
    });

    it('@catalogue: should be not show the Characteristics card when Characteristics settings was unchecked', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12429');

        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();

        cy.get('.sw-product-detail-specification__essential-characteristics').should('be.visible');

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(2).click();

        cy.get('.sw-product-detail-specification__essential-characteristics').should('not.be.visible');
    });

    it('@catalogue: should be not show the Custom Fields card when Custom Fields settings was unchecked', () => {
        cy.onlyOnFeature('FEATURE_NEXT_12429');

        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-tabs-item.sw-product-detail__tab-specifications').click();
        cy.get('.sw-product-detail-specification__essential-characteristics').scrollIntoView();

        cy.get('.sw-product-detail-specification__custom-fields').should('be.visible');

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(3).click();

        cy.get('.sw-product-detail-specification__custom-fields').should('not.be.visible');
    });
});
