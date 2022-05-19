// / <reference types="Cypress" />

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.intercept({
                    url: `${Cypress.env('apiPath')}/search/locale`,
                    method: 'POST'
                }).as('searchLocale');

                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

                cy.onlyOnFeature('FEATURE_NEXT_17950');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');

                cy.wait('@searchLocale')
                    .its('response.statusCode')
                    .should('equal', 200);
            });
    });
    it('@sdk: add menu item', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.get('.sw-dashboard-statistics__card-headline')
            .scrollIntoView();

        cy.get('.sw-dashboard-statistics__card-headline')
            .should('be.visible');

        cy.get('.sw-card-view__content')
            .scrollTo('bottom');

        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .should('be.visible');

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains('Hello from the new Menu Page')

        cy.get('.sw-page__search-bar')
            .should('not.exist');
    });
    it('@sdk: check menu position', ()=> {
        cy.onlyOnFeature('FEATURE_NEXT_17950');

        cy.get('.sw-card-view__content')
            .scrollTo('bottom');

        cy.get('.sw-dashboard-statistics__card-headline')
            .scrollIntoView();

        cy.get('.sw-dashboard-statistics__card-headline')
            .should('be.visible');

        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-extension')
            .click();

        cy.get('.sw-admin-menu__navigation-list-item')
            .contains('Store');

        cy.contains('.sw-admin-menu__navigation-link', 'Test with searchbar')
            .click();

        cy.log('Check if menu item is first instead of second');

        cy.get('.sw-admin-menu__item--sw-extension > .sw-admin-menu__sub-navigation-list > .navigation-list-item__type-plugin')
            .first()
            .contains('First item');
    });
})
