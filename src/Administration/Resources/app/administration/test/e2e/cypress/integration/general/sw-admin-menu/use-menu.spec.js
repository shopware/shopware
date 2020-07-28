/// <reference types="Cypress" />

import MenuPageObject from '../../../support/pages/module/sw-admin-menu.page-object';

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

    it('@navigation: toggle different menu appearances', () => {
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
});
