// / <reference types="Cypress" />

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/locale`,
            method: 'POST',
        }).as('searchLocale');

        // reset mouse position to neutral state
        cy.get('body').realHover({ position: 'topLeft' });

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('sw-main-hidden')
            .should('exist');

        cy.wait('@searchLocale')
            .its('response.statusCode')
            .should('equal', 200);

        cy.get('.navigation-list-item__type-plugin')
            .should('exist');

        cy.get('.navigation-list-item__type-plugin')
            .should('have.length.least', 3);
    });

    it('@sdk: add menu item', { tags: ['ct-admin', 'VUE3'] }, ()=> {
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
            .contains('Hello from the new Menu Page');

        cy.get('.sw-page__search-bar')
            .should('not.exist');
    });

    it('@sdk: add menu item at third level', { tags: ['ct-admin', 'VUE3'], browser: 'chrome' }, ()=> {
        cy.get('.sw-card-view__content')
            .scrollTo('bottom');

        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-catalogue')
            .click();

        cy.get('.sw-manufacturer')
            .should('be.visible')
            .realHover();

        cy.contains('.sw-admin-menu_flyout-holder', 'Third level')
            .should('be.visible')
            .click();

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .should('be.visible');

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .contains('Hello from the new Menu Page');

        /**  Information: The complete flyout can't be tested because when one hover gets triggered
         * the next one won't work in Cypress because the previous hover gets closed */
    });

    it('@sdk: check menu position', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.get('.sw-card-view__content')
            .scrollTo('bottom');

        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('.sw-extension')
            .click();

        cy.contains('.sw-admin-menu__navigation-list-item', 'Store');

        cy.contains('.sw-admin-menu__navigation-link', 'Test with searchbar')
            .click();

        cy.log('Check if menu item is first instead of second');

        cy.get('.sw-admin-menu__item--sw-extension > .sw-admin-menu__sub-navigation-list > .navigation-list-item__type-plugin')
            .first()
            .contains('First item');
    });
});
