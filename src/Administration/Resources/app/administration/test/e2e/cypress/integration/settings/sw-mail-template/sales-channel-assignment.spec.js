// / <reference types="Cypress" />

describe('Mail header & footer template: Sales Channel assignment', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('getData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-header-footer`,
            method: 'POST'
        }).as('saveTemplate');
    });

    it('@settings: Assign sales channel', () => {
        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });

        cy.get('#sw-mail-template').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

        cy.contains('.sw-card__title', 'Headers and Footers').should('exist').scrollIntoView().should('be.visible');

        cy.get('#mailHeaderFooterGrid').should('exist');
        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').should('have.length', 1);

        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').first().find('.sw-data-grid__actions-menu').click();
        cy.get('.sw-entity-listing__context-menu-edit-action').should('be.visible').click();

        cy.get('.sw-mail-header-footer-detail__sales-channel').click();
        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .find('.sw-select-result')
            .should('have.length', 2)
            .contains('.sw-select-result__result-item-text', 'Storefront')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.wait('@saveTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();
        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').first().find('.sw-data-grid__cell--salesChannels-name').contains('Storefront');
    });

    it('@settings: Re-assign sales channel to new header-footer template will pop up modal', () => {
        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });

        cy.get('#sw-mail-template').click();
        cy.wait('@getData')
    .its('response.statusCode').should('equal', 200);

        cy.contains('.sw-card__title', 'Headers and Footers').should('exist').scrollIntoView().should('be.visible');

        cy.get('#mailHeaderFooterGrid').should('exist');
        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').should('have.length', 1);

        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').first().find('.sw-data-grid__actions-menu').click();
        cy.get('.sw-entity-listing__context-menu-edit-action').should('be.visible').click();

        cy.get('.sw-mail-header-footer-detail__sales-channel').click();
        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .find('.sw-select-result')
            .should('have.length', 2)
            .contains('.sw-select-result__result-item-text', 'Storefront')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.wait('@saveTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();
        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').first().find('.sw-data-grid__cell--salesChannels-name').contains('Storefront');

        cy.contains('.sw-button__content', 'Add').click();
        cy.contains('.sw-context-menu-item', 'Add header and footer').click();

        cy.contains('.sw-card__title', 'Information').should('exist');
        cy.get('#sw-field--mailHeaderFooter-name').type('Example');

        cy.get('.sw-mail-header-footer-detail__sales-channel').click();
        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .find('.sw-select-result')
            .should('have.length', 2)
            .contains('.sw-select-result__result-item-text', 'Storefront')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-modal__header', 'Multiple assignments').should('be.visible');
        cy.contains('.sw-mail-header-footer-detail__sales-channel-list-entry', 'Storefront').should('be.visible');

        cy.get('.sw-modal__footer').contains('.sw-button-process__content', 'Save').click();

        cy.wait('@saveTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();
        cy.contains('.sw-data-grid__row', 'Example').should('exist').contains('.sw-data-grid__cell-content', 'Storefront');
    });

    it('@settings: Re-assign sales channel to existing header-footer template will pop up modal', () => {
        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });

        cy.get('#sw-mail-template').click();
        cy.wait('@getData')
    .its('response.statusCode').should('equal', 200);

        cy.contains('.sw-card__title', 'Headers and Footers').should('exist').scrollIntoView().should('be.visible');

        cy.get('#mailHeaderFooterGrid').should('exist');
        cy.get('#mailHeaderFooterGrid').find('.sw-data-grid__body .sw-data-grid__row').should('have.length', 1);

        cy.contains('.sw-button__content', 'Add').click();
        cy.contains('.sw-context-menu-item', 'Add header and footer').click();

        cy.contains('.sw-card__title', 'Information').should('exist');
        cy.get('#sw-field--mailHeaderFooter-name').type('Example');

        cy.get('.sw-mail-header-footer-detail__sales-channel').click();
        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .find('.sw-select-result')
            .should('have.length', 2)
            .contains('.sw-select-result__result-item-text', 'Storefront')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.wait('@saveTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();
        cy.contains('.sw-data-grid__row', 'Example').should('exist').contains('.sw-data-grid__cell-content', 'Storefront');

        cy.contains('.sw-data-grid__row', 'Default email footer').find('.sw-data-grid__actions-menu').click();
        cy.get('.sw-entity-listing__context-menu-edit-action').should('be.visible').click();

        cy.get('.sw-mail-header-footer-detail__sales-channel').click();
        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .find('.sw-select-result')
            .should('have.length', 2)
            .contains('.sw-select-result__result-item-text', 'Storefront')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.get('.sw-modal').should('be.visible');
        cy.contains('.sw-modal__header', 'Multiple assignments').should('be.visible');
        cy.contains('.sw-mail-header-footer-detail__sales-channel-list-entry', 'Storefront').should('be.visible');

        cy.get('.sw-modal__footer').contains('.sw-button-process__content', 'Save').click();

        cy.wait('@saveTemplate').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();

        cy.contains('.sw-data-grid__row', 'Default email footer').should('exist').contains('.sw-data-grid__cell-content', 'Storefront');
    });
});
