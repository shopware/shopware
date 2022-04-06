describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/index/shop`);

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');
            });
    });
    it('@sdk: add settings without searchbar', () => {
        cy.get('.sw-settings__content-header')
            .contains('Settings');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('#ui-menu-item-add-menu-item')
            .should('exist')
            .contains('Without searchbar')
            .click();

        cy.get('.smart-bar__content')
            .contains('Without searchbar');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .contains('Hello from the new Menu Page')

        cy.get('.sw-page__search-bar')
            .should('not.exist');
    });
    it('@sdk: add settings with searchbar', () => {
        cy.get('.sw-settings')
            .click();
        cy.get('.sw-settings__tab-plugins')
            .click();

        cy.get('.sw-settings__content-header')
            .contains('Settings');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('#ui-menu-item-add-menu-item-with-searchbar')
            .should('exist')
            .contains('App Settings')
            .click();

        cy.get('.smart-bar__content')
            .contains('App Settings');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .contains('Hello from the new menu page with searchbar')

        cy.get('.sw-page__search-bar')
            .should('exist');
    })
});
