// / <reference types="Cypress" />

describe('Product: Test filter variants', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createPropertyFixture({
                    name: 'Size',
                    options: [{ name: 'S' }, { name: 'M' }, { name: 'L' }]
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'Parent Product'
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);

                cy.get('.sw-data-grid__cell--name')
                    .click();

                cy.get('.sw-product-detail__tab-variants').click();

                cy.get('.sw-product-detail-variants__generated-variants__empty-state .sw-button')
                    .click();

                cy.get('.sw-grid__row--0 .group_grid__column-name')
                    .click();

                cy.get('.sw-property-search__tree-selection__option_grid .sw-grid__row--0 > :nth-child(2)').click();
                cy.get('.sw-property-search__tree-selection__option_grid .sw-grid__row--1 > :nth-child(2)').click();

                cy.get('.sw-product-variant-generation__generate-action').click();

                cy.get('.sw-product-modal-variant-generation__notification-modal .sw-button--primary').click();

                cy.get('.sw-modal').should('not.exist');
            });
    });

    it('@catalogue: should filter options by properties', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/product`,
            method: 'POST'
        }).as('searchVariants');

        cy.get('.sw-product-variants-overview__filter-list-button > .sw-button')
            .should('be.visible')
            .click();

        cy.get('.sw-context-menu .sw-context-menu__content').should('be.visible');

        cy.get('.tree-items > .sw-tree-item')
            .eq(0)
            .find('.sw-tree-item__toggle')
            .click();

        cy.get('.tree-items > .sw-tree-item')
            .eq(0)
            .get('.sw-tree-item__children')
            .should('be.visible');

        cy.get('.sw-tree-item__children > .sw-tree-item')
            .eq(0)
            .get('.sw-tree-item__element')
            .contains('L');

        cy.get('.sw-tree-item__children > .sw-tree-item')
            .eq(1)
            .get('.sw-tree-item__element')
            .contains('M');

        cy.get('.sw-tree-item__children > .sw-tree-item')
            .eq(0)
            .find('.sw-tree-item__element > :nth-child(2)')
            .click();

        cy.get('.sw-context-menu .sw-context-menu__content')
            .find('.sw-button--primary')
            .click();

        cy.wait('@searchVariants').its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__row--1').should('not.exist');

        cy.get('.sw-context-menu .sw-context-menu__content')
            .get('.sw-button')
            .contains('Reset')
            .click();

        cy.wait('@searchVariants').its('response.statusCode').should('equal', 200);

        cy.get('.sw-data-grid__row--0').should('be.visible');
        cy.get('.sw-data-grid__row--1').should('be.visible');
    });
});
