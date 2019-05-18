/// <reference types="Cypress" />

import SettingsPageObject from '../../support/pages/module/sw-settings.page-object';

describe('Administration: Check module navigation in settings', () => {

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

    it('navigate to tax module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/tax',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-tax').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-page__main-content').should('be.visible');
        });
    });

    it('navigate to snippet module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/snippet-set',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-snippet').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-snippet-set-list__actions').should('be.visible');
        });
    });

    it('@p navigate to shipping module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/shipping-method',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-shipping').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-shipping-list__content').should('exist');
        });
    });

    it('navigate to salutation module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/salutation',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-salutation').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-salutation-list-grid').should('be.visible');
        });
    });

    it('@p navigate to rule builder module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/rule',
            method: 'post'
        }).as('getData');


        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-rule').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-rule-list__content').should('exist');
        });
    });

    it('@p navigate to payment module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/payment-method?page=1&limit=25',
            method: 'get'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-payment').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-payment-list').should('be.visible');
        });
    });

    it('navigate to number ranges module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/number-range',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-number-range').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-number-range-list-grid').should('be.visible');
        });
    });

    it('navigate to language module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/language',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-language').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-language-list').should('be.visible');
        });
    });

    it('navigate to customer group module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/customer-group',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-customer-group').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-customer-group-list-grid').should('be.visible');
        });
    });

    it('navigate to currency module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/currency',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-currency').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-currency-list-grid').should('be.visible');
        });
    });

    it('navigate to country module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/country',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-settings-country').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-settings-country-list-grid').should('be.visible');
        });
    });

    it('@p navigate to plugin module', () => {
        cy.server();
        cy.route({
            url: '/api/v1/search/plugin',
            method: 'post'
        }).as('getData');

        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('.sw-tabs-item[title="System"]').click();
        cy.get('a[href="#/sw/plugin/index"]').click();
        cy.wait('@getData').then(() => {
            cy.get('.sw-plugin-list').should('be.visible');
        });
    });
});
