/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Theme: Test sales channel assignment', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.createDefaultSalesChannel().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @content: basic sales-channel assignment works', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .should('exist')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();
        cy.contains('.sw-button-process__content', 'Save').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 3);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');
    });

    it('@content: can\'t remove saved sales-channel from default theme', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*/configuration`,
            method: 'GET',
        }).as('loadData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.wait('@loadData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();

        cy.contains('.sw-button-process__content', 'Save').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 3);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list .sw-select-result')
            .should('have.length', 3)
            .should('have.class', 'is--disabled');
    });

    it('@content: can remove unsaved sales-channel from default theme', { tags: ['pa-sales-channels', 'quarantined'] }, () => {
        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 3);

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .should('not.have.class', 'is--disabled');

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Storefront')
            .should('have.class', 'is--disabled');

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 2);
    });

    it('@content: can remove saved sales-channel from non-default theme', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__context-button').click();
        cy.contains('.sw-context-menu-item', 'Create duplicate').click();

        cy.get('[name="sw-field--duplicate-theme-name"]').type('New theme');
        cy.contains('.sw-modal__footer > .sw-button--primary', 'Create duplicate').click();

        cy.contains('.sw-theme-manager-detail__info-name', 'New theme').should('be.visible');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 1);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();


        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Storefront')
            .should('not.have.class', 'is--disabled');

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .should('not.have.class', 'is--disabled');

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 0);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('not.exist');
    });

    it('@content: shows warning in modal when sales-channel is re-assigned', { tags: ['pa-sales-channels', 'quarantined'] }, () => {
        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__context-button').click();
        cy.contains('.sw-context-menu-item', 'Create duplicate').click();

        cy.get('[name="sw-field--duplicate-theme-name"]').type('New theme');
        cy.contains('.sw-modal__footer > .sw-button--primary', 'Create duplicate').click();

        cy.contains('.sw-theme-manager-detail__info-name', 'New theme').should('be.visible');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Storefront')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();
        cy.contains('.sw-alert__message', 'This Sales Channel is already assigned').should('be.visible');
        cy.contains('.sw-alert__message', 'Shopware default theme (Storefront)').should('be.visible');
    });

    it('@content: shows warning in modal when sales-channel is removed from non-default theme', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__context-button').click();
        cy.contains('.sw-context-menu-item', 'Create duplicate').click();

        cy.get('[name="sw-field--duplicate-theme-name"]').type('New theme');
        cy.contains('.sw-modal__footer > .sw-button--primary', 'Create duplicate').click();

        cy.contains('.sw-theme-manager-detail__info-name', 'New theme').should('be.visible');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 1);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();
        cy.contains('.sw-alert__message', 'You have removed a theme assignment').should('be.visible');
        cy.contains('.sw-alert__message', 'New theme (Channel No 9)').should('be.visible');
    });

    it('@content: removing sales-channel from non-default theme will assign it to default theme', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__context-button').click();
        cy.contains('.sw-context-menu-item', 'Create duplicate').click();

        cy.get('[name="sw-field--duplicate-theme-name"]').type('New theme');
        cy.contains('.sw-modal__footer > .sw-button--primary', 'Create duplicate').click();

        cy.contains('.sw-theme-manager-detail__info-name', 'New theme').should('be.visible');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.get('.sw-select-result-list__item-list')
            .contains('.sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 1);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');

        cy.get('.sw-theme-manager-detail__saleschannels-select')
            .get('.sw-select__selection')
            .click();

        cy.contains('.sw-select-result-list__item-list .sw-select-result', 'Channel No 9')
            .click();

        cy.get('.sw_theme_manager_detail__save-action').click();
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.get('.sw-button__loader').should('not.exist');
        cy.get('.smart-bar__back-btn').click();

        cy.get('.sw-theme-list-item')
            .last()
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__saleschannels-select .sw-select-selection-list__item-holder').should('have.length', 3);
        cy.contains('.sw-select-selection-list__item-holder', 'Channel No 9').should('exist');
    });
});
