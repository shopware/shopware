describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/index/shop`);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('sw-main-hidden')
            .should('exist');
    });
    it('@sdk: add settings without searchbar', { tags: ['ct-admin', 'VUE3'] }, () => {
        cy.contains('.sw-settings__content-header', 'Settings');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.contains('#ui-menu-item-add-menu-item', 'Without searchbar')
            .should('exist');
        cy.contains('#ui-menu-item-add-menu-item', 'Without searchbar')
            .click();

        cy.contains('.smart-bar__content', 'Without searchbar');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .contains('Hello from the new Menu Page');

        cy.get('.sw-page__search-bar')
            .should('not.exist');
    });
    it('@sdk: add settings with searchbar', { tags: ['ct-admin', 'VUE3'] }, () => {
        cy.get('.sw-settings')
            .click();
        cy.get('.sw-settings__tab-plugins')
            .click();

        cy.contains('.sw-settings__content-header', 'Settings');
        cy.get('.sw-loader')
            .should('not.exist');
        cy.get('.sw-skeleton')
            .should('not.exist');

        cy.get('#ui-menu-item-add-menu-item-with-searchbar')
            .should('exist')
            .contains('App Settings')
            .click();

        cy.contains('.smart-bar__content', 'App Settings');

        cy.getSDKiFrame('ui-menu-item-add-menu-item-with-searchbar')
            .should('be.visible');

        cy.getSDKiFrame('ui-menu-item-add-menu-item')
            .contains('Hello from the new menu page with searchbar');

        cy.get('.sw-page__search-bar')
            .should('exist');
    });
});
