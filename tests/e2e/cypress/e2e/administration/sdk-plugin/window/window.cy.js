// / <reference types="Cypress" />

import ProductPageObject from "../../../../support/pages/module/sw-product.page-object";

const page = new ProductPageObject();

describe('SDK Tests: Window', ()=> {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            })
            .then(() => {
                cy.intercept({
                    url: `${Cypress.env('apiPath')}/search/locale`,
                    method: 'POST'
                }).as('searchLocale');

                cy.get('.sw-loader').should('not.exist');
                cy.get('.sw-skeleton').should('not.exist');

                cy.getSDKiFrame('sw-main-hidden')
                    .should('exist');

                cy.wait('@searchLocale')
                    .its('response.statusCode')
                    .should('equal', 200);

                cy.get('.navigation-list-item__type-plugin')
                    .should('exist');

                cy.get('.navigation-list-item__type-plugin')
                    .should('have.length', 3);
            })
    });

    it('@sdk: redirect to another URL', { tags: ['ct-admin'] }, ()=> {
        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Redirect URL')

        cy.window().then(win => {
            cy.stub(win, 'open').as('Open')
        })

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Dispatch a notification')
            .click();

        cy.contains('.sw-button__content', 'Redirect to Shopware')
            .click();

        cy.get('@Open').should('have.been.calledOnceWith', 'https://www.shopware.com')
    })

    it('@sdk: reload page', { tags: ['ct-admin'] }, ()=> {
        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Reload page URL')

        cy.window().then(win => {
            win.beforeReload = true;
        })

        cy.window().should('have.prop', 'beforeReload', true)

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Reload page')
            .click();

        cy.window().should('not.have.prop', 'beforeReload', true)
    })

    it('@sdk: push router', { tags: ['ct-admin'] }, ()=> {
        cy.log('Go to extension page')

        cy.get('.sw-admin-menu__item--sw-order')
            .click();

        cy.contains('.sw-admin-menu__navigation-link', 'Test item')
            .click();

        cy.log('Push to dashboard')

        cy.getSDKiFrame('ui-main-module-add-main-module')
            .find('button')
            .contains('Push route')
            .click();

    })
})
