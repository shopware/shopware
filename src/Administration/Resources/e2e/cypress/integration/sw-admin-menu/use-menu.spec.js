/// <reference types="Cypress" />

import MenuPageObject from '../../support/pages/module/sw-admin-menu.page-object';

describe('Admin menu: Toggle different admin menu appearances, change and assert administration language', () => {
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi()
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('toggle different menu appearances', () => {
        const page = new MenuPageObject();

        // Check expanded and collapsed menu
        cy.get('.sw-admin-menu__user-actions-toggle .sw-loader').should('not.exist');
        cy.get('.sw-admin-menu__item--sw-dashboard .sw-admin-menu__navigation-link').click();
        cy.get('.sw-admin-menu__user-actions-toggle .sw-loader').should('not.exist');
        cy.openUserActionMenu();
        cy.get(page.elements.menuToggleAction).click();
        cy.get('.sw-admin-menu.is--collapsed').should('be.visible');
        cy.get(page.elements.menuToggleAction).click();
        cy.get('.sw-admin-menu.is--expanded').should('be.visible');
    });

    it.skip('change and assert language', () => {
        const page = new MenuPageObject();

        cy.get('.sw-admin-menu__user-actions-toggle .sw-loader').should('not.exist');
        cy.get('.sw-search-bar__input').should(
            'have.attr',
            'placeholder',
            'Find products, customers, orders...'
        );
        cy.get('.sw-admin-menu__item--sw-dashboard .sw-admin-menu__navigation-link').click();
        cy.get('.sw-admin-menu__user-actions-toggle .sw-loader').should('not.exist');
        cy.openUserActionMenu();

        // Check change of admin language via menu button
        cy.get(page.elements.languageAction).click();
        cy.get('.sw-dashboard-card-headline').contains('Willkommen');
        cy.get(page.elements.languageAction).contains('Sprache wechseln');
        cy.get(page.elements.languageAction).click();
        cy.get('.sw-dashboard-card-headline').contains('Welcome');
        cy.get(page.elements.languageAction).contains('Change language');
    });
});
