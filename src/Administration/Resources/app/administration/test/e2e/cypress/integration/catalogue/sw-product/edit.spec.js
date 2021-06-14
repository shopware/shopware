/// <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Product: Edit in various ways', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: edit a product\'s translation', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        page.changeTranslation('Deutsch', 0);
        cy.get(page.elements.loader).should('not.exist');
        cy.get('.sw_language-info__info').contains('"Product name" displayed in the content language');
        cy.get('.sw_language-info__info').contains('span', '"Deutsch"');
        cy.get('.sw_language-info__info').contains('Fallback is the system default language');
        cy.get('input[name=sw-field--product-name]').type('Sauerkraut');
        cy.get(page.elements.productSaveAction).click();

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).contains('Sauerkraut');
    });

    it('@catalogue: edit product via inline edit', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/product/*`,
            method: 'patch'
        }).as('saveData');

        // Inline edit customer
        cy.get('.sw-data-grid__cell--productNumber').dblclick();
        cy.get('#sw-field--item-name').clearTypeAndCheck('That\'s not my name');
        cy.get('.sw-data-grid__inline-edit-save').click();
        cy.awaitAndCheckNotification('Product "That\'s not my name" has been saved.');

        // Verify updated product
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-data-grid__cell--name').contains('That\'s not my name');
    });
});
