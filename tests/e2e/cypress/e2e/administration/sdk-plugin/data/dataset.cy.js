// / <reference types="Cypress" />

describe('Category: SDK Test', ()=> {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/index/shop`);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('sw-main-hidden')
            .should('exist');
    });

    it('@sdk: dataset get', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront')
            .click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('Get dataset');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('button', 'Get dataset')
            .click();

        cy.get('.sw-card-view__content')
            .scrollTo("bottom");

        cy.getSDKiFrame('data-dataset')
            .contains('Returned name: Storefront');

        cy.getSDKiFrame('data-dataset')
            .contains('Returned active state: true');
    });

    it('@sdk: dataset subscribe', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront')
            .click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('Get dataset');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('button', 'Subscribe dataset')
            .click();

        cy.get('.sw-card-view__content')
            .scrollTo("bottom");

        cy.getSDKiFrame('data-dataset')
            .contains('Returned name: Storefront');
    });

    it('@sdk: dataset update', { tags: ['ct-admin', 'VUE3'] }, ()=> {
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront')
            .click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('Get dataset');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.getSDKiFrame('data-dataset')
            .contains('button', 'Get dataset')
            .click();

        cy.get('.sw-card-view__content')
            .scrollTo("bottom");

        cy.getSDKiFrame('data-dataset')
            .contains('Returned name: Storefront');

        cy.getSDKiFrame('data-dataset')
            .find('input')
            .clear()
            .type('Shoppingfront');

        cy.getSDKiFrame('data-dataset')
            .contains('button', 'Update to main')
            .click();

        cy.get('.smart-bar__header')
            .should('have.text', 'Shoppingfront');
    });
});
