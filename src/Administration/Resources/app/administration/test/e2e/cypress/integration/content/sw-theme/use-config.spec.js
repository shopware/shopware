// / <reference types="Cypress" />

import ProductPageObject from '../../../support/pages/module/sw-product.page-object';

describe('Theme: Test loading and saving of theme', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            });
    });

    it('@base @content: opens and loads theme config', () => {
        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area').its('length').should('be.gte', 1);

        // Check whether logo media inputs are full width
        cy.get('.sw-theme-manager-detail__content--section_field-full-width').contains('Desktop');
        cy.get('.sw-theme-manager-detail__content--section_field-full-width').contains('Tablet');
        cy.get('.sw-theme-manager-detail__content--section_field-full-width').contains('Mobile');
    });

    it('@base @content: rename theme', () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.clickContextMenuItem(
            '.sw-context-menu-item:nth-of-type(1)',
            `.sw-theme-manager-detail__info-card ${page.elements.contextMenuButton}`
        );

        cy.get('.sw-modal').should('be.visible');
        cy.get('#sw-field--newThemeName').clear();
        cy.get('#sw-field--newThemeName').type('Lovski Theme');

        cy.get('.sw-modal .sw-button--primary').click();

        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-theme-manager-detail__info-name').contains('Lovski Theme');
    });
});
