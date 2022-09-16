// / <reference types="Cypress" />

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/extension/my-extensions/listing/`);

                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');

                cy.get('.navigation-list-item__type-plugin')
                    .should('exist');

                cy.get('.navigation-list-item__type-plugin')
                    .should('have.length', 3);
            });
    });

    it('@sdk: add main module', { tags: ['ct-admin'] }, ()=> {
        cy.get('.sw-meteor-page__smart-bar-title')
            .should('be.visible');
        cy.contains('.sw-meteor-page__smart-bar-title', 'My extensions');
        cy.get('.sw-skeleton')
            .should('not.exist');
        cy.get('.sw-loader')
            .should('not.exist');

        cy.get('.sw-meteor-card__content-wrapper > .sw-context-button > .sw-context-button__button')
            .click();

        cy.get('.sw-context-menu__content').contains('Open extension')
            .click();

        cy.contains('.smart-bar__content', 'My App');
    });

    it('@sdk: check main module with searchbar', { tags: ['ct-admin'] }, ()=> {
        cy.get('.sw-meteor-page__smart-bar-title')
            .should('be.visible');
        cy.contains('.sw-meteor-page__smart-bar-title', 'My extensions');
        cy.get('.sw-skeleton')
            .should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.navigation-list-item__sw-extension')
            .click();

        cy.contains('.sw-admin-menu__navigation-list-item', 'Store');

        cy.get('.navigation-list-item__type-plugin').contains('Test with searchbar')
            .click();

        cy.contains('.smart-bar__content', 'Test with searchbar');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .contains('Hello from the new menu page with searchbar');

        cy.get('.sw-page__search-bar')
            .should('be.visible');
    });
});
