// / <reference types="Cypress" />

import RulePageObject from '../../../../support/pages/module/sw-rule.page-object';

describe('Rule builder: Use advanced selection for entity selection', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createDefaultFixture('rule');
            })
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'another product',
                    productNumber: 'RS-334',
                });
            })
            .then(() => {
                return cy.createProductFixture({
                    name: 'something nice',
                    productNumber: 'RS-335',
                    active: false,
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/rule/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@rule: Use advanced selection for product selection inside item condition', { tags: ['pa-business-ops'] }, () => {
        const page = new RulePageObject();
        cy.contains('.sw-data-grid__cell-value', 'Ruler').click();

        // add the "item" condition
        cy.get('.sw-condition-and-container').should('be.visible');
        page.selectTypeAndOperator('.sw-condition-and-container .sw-condition', 'Items in cart', 'Is one of');

        // open advanced selection modal
        cy.get('.sw-condition-and-container .sw-select-selection-list__input').click();
        cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-select-result-list-popover-wrapper .sw-multi-select-filtering__advanced-selection').click();

        // inside advanced selection modal select all elements and apply
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 3);
        cy.get('.sw-entity-advanced-selection-modal').should('be.visible');
        cy.get('.sw-data-grid__cell--selection input').check();
        cy.get('.sw-entity-advanced-selection-modal__button-apply').click();

        // check input
        cy.get('.sw-select-selection-list__item-holder').should('have.length', 3);
        cy.contains('.sw-select-selection-list__item-holder', 'something nice').should('exist');
        cy.contains('.sw-select-selection-list__item-holder', 'Product name').should('exist');
        cy.contains('.sw-select-selection-list__item-holder', 'another product').should('exist');

        // open advanced selection modal again
        cy.get('.sw-condition-and-container .sw-select-selection-list__input').click();
        cy.get('.sw-select-result-list-popover-wrapper').should('be.visible');
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-select-result-list-popover-wrapper .sw-multi-select-filtering__advanced-selection').click();
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');

        // apply a filter and make a selection
        cy.get('.sw-entity-advanced-selection-modal__filter-list-button').click();
        cy.get('#active-filter select').select('Inactive');
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        // deselect one product
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 1);
        cy.get('.sw-data-grid__cell--selection input').uncheck();
        // resetting all filters should work as expected
        cy.get('.sw-entity-advanced-selection-modal__filter-reset').click();
        // wait for ending loading state
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-data-grid__body .sw-data-grid__row').should('have.length', 3);
        cy.get('.sw-entity-advanced-selection-modal__button-apply').click();

        // this should have deselected the one deselected product
        cy.get('.sw-select-selection-list__item-holder').should('have.length', 2);
        cy.contains('.sw-select-selection-list__item-holder', 'something nice').should('not.exist');
    });
});
