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

    it('@catalogue: should be not show fields in Price card when Price settings was unchecked', () => {
        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        const priceFieldsClassName = [
            '.sw-purchase-price-field',
            '.sw-price-field.sw-list-price-field__list-price'
        ];

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(1).click();
        cy.get('.sw-product-price-form').scrollIntoView();

        priceFieldsClassName.forEach(item => {
            cy.get(item).should('not.be.visible');
        });
    });

    it('@catalogue: should be not show fields in Deliverability card when Deliverability settings was unchecked', () => {
        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        const deliveryFieldsClassName = [
            '.product-deliverability-form__delivery-time',
            '.sw-product-deliverability__restock-field',
            '.sw-product-deliverability__shipping-free',
            '.sw-product-deliverability__min-purchase',
            '.sw-product-deliverability__purchase-step',
            '.sw-product-deliverability__max-purchase'
        ];

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(2).click();
        cy.get('.product-deliverability-form').scrollIntoView();

        deliveryFieldsClassName.forEach(item => {
            cy.get(item).should('not.be.visible');
        });
    });

    it('@catalogue: should be not show fields in Structure card when Structure settings was unchecked', () => {
        const page = new ProductPageObject();
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );
        const structureFieldsClassName = [
            '.sw-product-category-form__tag-field-wrapper',
            '.sw-product-category-form__search-keyword-field'
        ];

        cy.get('.sw-product-settings-mode').click();
        cy.get('.sw-product-settings-mode__list .sw-product-settings-mode__item').eq(3).click();
        cy.get('.sw-product-category-form').scrollIntoView();

        structureFieldsClassName.forEach(item => {
            cy.get(item).should('not.be.visible');
        });
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
