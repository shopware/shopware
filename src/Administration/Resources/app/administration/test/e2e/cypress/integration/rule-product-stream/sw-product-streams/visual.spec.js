// / <reference types="Cypress" />

import ProductStreamObject from '../../../support/pages/module/sw-product-stream.page-object';

describe('Dynamic product groups: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('product-stream');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/stream/index`);
            });
    });

    it('@visual: check appearance of basic product stream workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'post'
        }).as('searchProducts');

        const page = new ProductStreamObject();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__row--0').should('be.visible');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling(
            '.sw-data-grid__cell--updatedAt',
            'color: #fff'
        );
        cy.get('.sw-data-grid__cell--updatedAt')
            .should('have.css', 'color', 'rgb(255, 255, 255)');
        cy.takeSnapshot('[Product groups] Listing', '.sw-product-stream-list');

        cy.get(page.elements.smartBarHeader).contains('Dynamic product groups');

        // Verify product stream details
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get(page.elements.smartBarHeader).contains('1st Productstream');
        cy.get(page.elements.loader).should('not.exist');

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is equal to any of',
                value: ['Product name']
            }
        );

        cy.get('button.sw-button').contains('Preview').click();

        // Take snapshot for visual testing
        cy.get('.sw-product-stream-modal-preview').should('be.visible');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.wait('@searchProducts').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('.sw-data-grid .sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__header').contains('Preview (1)');

        cy.handleModalSnapshot('Preview');
        cy.takeSnapshot('[Product groups] Detail, preview', '.sw-product-stream-modal-preview .sw-data-grid__row--0');

        cy.get('.sw-product-stream-modal-preview').within(() => {
            cy.get('.sw-data-grid .sw-data-grid__row--0 .sw-data-grid__cell--name').contains('Product name');
            cy.get('.sw-modal__close').click();
        });

        page.fillFilterWithEntityMultiSelect(
            '.sw-product-stream-filter',
            {
                field: null,
                operator: 'Is not equal to any of',
                value: []
            }
        );

        // Take snapshot for visual testing
        cy.get('.sw-select-selection-list__input').type('{esc}');
        cy.takeSnapshot('[Product groups] Detail, with conditions', '.sw-product-stream-detail');

        cy.get('.sw-condition-or-container__actions > :nth-child(1) > .sw-button')
            .click();

        cy.get(':nth-child(3) > .sw-product-stream-filter > .sw-product-stream-filter__container > .sw-product-stream-filter__selects > .sw-product-stream-field-select > .sw-field > .sw-block-field__block > .sw-select__selection > .sw-single-select__selection')
            .click();

        cy.get('.sw-select-result-list__content')
            .should('be.visible');

        cy.takeSnapshot('[Product groups] Detail, with popover menu', '.sw-product-stream-detail');
    });
});
