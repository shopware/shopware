/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Theme: Test loading and saving of theme', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.viewport(1920, 1080);
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@base @content: opens and loads theme config', { tags: ['pa-sales-channels'] }, () => {
        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area').its('length').should('be.gte', 1);

        // Check whether logo media inputs are full width
        cy.contains('.sw-theme-manager-detail__content--section_field-full-width', 'Desktop');
        cy.contains('.sw-theme-manager-detail__content--section_field-full-width', 'Tablet');
        cy.contains('.sw-theme-manager-detail__content--section_field-full-width', 'Mobile');
    });

    it('@base @content: rename theme', { tags: ['pa-sales-channels'] }, () => {
        const page = new ProductPageObject();
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.clickContextMenuItem(
            '.sw-context-menu-item:nth-of-type(1)',
            `.sw-theme-manager-detail__info-card ${page.elements.contextMenuButton}`,
        );

        cy.get('.sw-modal').should('be.visible');
        cy.get('[name="sw-field--rename-theme-name"]').clear();
        cy.get('[name="sw-field--rename-theme-name"]').type('Lovski Theme');

        cy.get('.sw-modal .sw-button--primary').click();

        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.contains('.sw-theme-manager-detail__info-name', 'Lovski Theme');
    });
});
