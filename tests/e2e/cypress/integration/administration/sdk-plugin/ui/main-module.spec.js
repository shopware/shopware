// / <reference types="Cypress" />

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/extension/my-extensions/listing/`);

                cy.onlyOnFeature('FEATURE_NEXT_17950');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            });
    });
    it('@sdk: add main module', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.get('.sw-meteor-page__smart-bar-title')
            .should('be.visible');
        cy.get('.sw-meteor-page__smart-bar-title')
            .contains('My extensions');
        cy.get('.sw-skeleton')
            .should('not.exist');
        cy.get('.sw-loader')
            .should('not.exist');

        cy.get('.sw-meteor-card__content-wrapper > .sw-context-button > .sw-context-button__button')
            .click();

        cy.get('.sw-context-menu__content')
            .contains('Open extension')
            .click();

        cy.get('.smart-bar__content')
            .contains('My App');
    });
    it('@sdk: check main module with searchbar', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.get('.sw-meteor-page__smart-bar-title')
            .should('be.visible');
        cy.get('.sw-meteor-page__smart-bar-title')
            .contains('My extensions');
        cy.get('.sw-skeleton')
            .should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.navigation-list-item__sw-extension')
            .click();

        cy.get('.sw-admin-menu__navigation-list-item')
            .contains('Store');

        cy.get('.navigation-list-item__type-plugin')
            .contains('Test with searchbar')
            .click();

        cy.get('.smart-bar__content')
            .contains('Test with searchbar');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .contains('Hello from the new menu page with searchbar');

        cy.get('.sw-page__search-bar')
            .should('be.visible');
    });
});
