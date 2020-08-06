/// <reference types="Cypress" />

import CategoryPageObject from '../../../support/pages/module/sw-category.page-object';

describe('Category: Edit categories', () => {
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState();
    });

    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
            });
    });

    it('@catalogue: change content language without selected category', () => {
        const page = new CategoryPageObject();

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');

        page.changeTranslation('Deutsch', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');

        page.changeTranslation('English', 1);
        cy.get('.sw-tree-item__label').should('be.visible');
        cy.get('.sw-empty-state__element').should('be.visible');

        cy.get('.sw-tree-item__label')
            .should('be.visible')
            .contains('Home');
    });

    it('@catalogue: change content language with selected category', () => {
        const page = new CategoryPageObject();

        cy.get('.sw-tree-item__label')
            .contains('Home')
            .click();

        cy.get('#categoryName')
            .should('be.visible')
            .should('have.value', 'Home');

        page.changeTranslation('Deutsch', 1);
        cy.get('.sw-loader').should('not.exist');
        cy.get('#categoryName')
            .should('be.visible')
            .should('have.value', 'Home');
    });
});
