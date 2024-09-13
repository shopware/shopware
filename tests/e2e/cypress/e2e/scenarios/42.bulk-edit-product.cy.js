/// <reference types="Cypress" />

import ProductPageObject from '../../support/pages/module/sw-product.page-object';

describe('Bulk Edit - Products', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createPropertyFixture({
                name: 'Size',
                options: [{name: 'S'}, {name: 'M'}, {name: 'L'}],
            });
        }).then(() => {
            cy.createProductFixture({
                name: 'Test Product',
                productNumber: 'TS-444',
                price: [{
                    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    linked: true,
                    gross: 60,
                }],
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
        });
    });

    it('@package: should modify products with bulk edit functionality', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/search/user-config`,
            method: 'POST',
        }).as('getUserConfig');

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST',
        }).as('saveData');

        const page = new ProductPageObject();
        const propertyValue = '.sw-property-search__tree-selection__option_grid';

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid__select-all .sw-field__checkbox input').click();
        cy.get('.sw-data-grid__bulk-selected.bulk-link').should('exist');
        cy.get('.link.link-primary').click();
        cy.wait('@getUserConfig').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Make changes on both product
        cy.get('.sw-product-bulk-edit-modal').should('exist');
        cy.get('.sw-modal__footer .sw-button--primary').click();
        cy.contains('.smart-bar__header', 'Bulk bewerking: 2 producten');
        cy.get('.sw-bulk-edit-change-field-description [type]').click();
        cy.get('.sw-text-editor__content-editor').clear().type('Bulk edit test');

        // Properties
        cy.get('.sw-bulk-edit-change-field-properties .sw-bulk-edit-change-field__change [type]').click();
        cy.get('.sw-tooltip--wrapper > .sw-button').click();
        cy.get('#modalTitleEl').should('be.visible');
        cy.contains('Size').click();
        cy.get(`${propertyValue} .sw-grid__cell-content`).should('be.visible');
        cy.get(`${propertyValue} .sw-grid__row--0 input`).click();
        cy.get(`${propertyValue} .sw-grid__row--1 input`).click();
        cy.get('.sw-property-search__tree-selection__column-items-selected').should('include.text', '2');
        cy.get('.sw-product-add-properties-modal__button-save').click();
        cy.get('.sw-data-grid__cell-value').should('include.text', 'Size');

        // Restock time
        cy.get('.sw-bulk-edit-change-field-restockTime [type="checkbox"]').click();
        cy.get('input#restockTime').clearTypeAndCheck('30');

        // Min order quantity
        cy.get('.sw-bulk-edit-change-field-minPurchase [type="checkbox"]').click();
        cy.get('input#minPurchase').clearTypeAndCheck('10');

        // Visibility
        cy.get('.sw-bulk-edit-change-field-visibilities [type="checkbox"]').click();
        cy.get('div[name="visibilities"]').typeMultiSelectAndCheck(Cypress.env('storefrontName'));

        // SEO
        cy.get('.sw-bulk-edit-change-field-metaTitle [type="checkbox"]').click();
        cy.get('input#metaTitle').clearTypeAndCheck('The best products ever');

        // Save and apply changes
        cy.get('.sw-bulk-edit-product__save-action').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.footer-right .sw-button--primary', 'Wijzigingen toepassen');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-bulk-edit-save-modal').should('exist');
        cy.contains('.sw-bulk-edit-save-modal', 'Bulk edit - Succes');
        cy.contains('.footer-right .sw-button--primary', 'Sluiten');
        cy.get('.footer-right .sw-button--primary').click();
        cy.get('.sw-bulk-edit-save-modal').should('not.exist');

        // Verify from the product details
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );
        cy.get('.sw-text-editor__content-editor').should('include.text', 'Bulk edit test');
        cy.get('.sw-product-detail-base__deliverability .sw-card__title').scrollIntoView();
        cy.get('#sw-field--product-restock-time').should('have.value', '30');
        cy.get('.sw-product-deliverability__min-purchase [type]').should('have.value', '10');
        cy.contains('.sw-product-category-form__visibility_field', Cypress.env('storefrontName'));

        cy.contains('specificaties').click();
        cy.get('.sw-product-properties__card .sw-card__title').scrollIntoView();
        cy.get('.sw-product-properties__card .sw-card__title').should('be.visible');
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').should('be.visible');

        cy.contains('SEO').scrollIntoView();
        cy.contains('SEO').click();
        cy.get('[placeholder="Voer een meta title in ..."]')
            .should('have.value', 'The best products ever');

        // Verify from the second product details
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.url().should('include', 'product/index');
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--1`,
        );
        cy.get('.sw-text-editor__content-editor').should('include.text', 'Bulk edit test');
        cy.get('.sw-product-detail-base__deliverability .sw-card__title').scrollIntoView();
        cy.get('#sw-field--product-restock-time').should('have.value', '30');
        cy.get('.sw-product-deliverability__min-purchase [type]').should('have.value', '10');
        cy.contains('.sw-product-category-form__visibility_field', Cypress.env('storefrontName'));

        cy.contains('specificaties').scrollIntoView();
        cy.contains('specificaties').click();
        cy.get('.sw-product-properties__card .sw-card__title').scrollIntoView();
        cy.get('.sw-product-properties__card .sw-card__title').should('be.visible');
        cy.get('.sw-data-grid__cell--name > .sw-data-grid__cell-content').should('be.visible');

        cy.contains('SEO').scrollIntoView();
        cy.contains('SEO').click();
        cy.get('[placeholder="Voer een meta title in ..."]')
            .should('have.value', 'The best products ever');
    });
});
