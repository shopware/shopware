// / <reference types="Cypress" />

describe('Theme: Test common editing of theme', () => {
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

    it('@base @media @content: change theme logo image', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.get('.sw-media-upload-v2')
            .first()
            .contains('Desktop');

        cy.get('.sw-media-upload-v2 .sw-media-upload-v2__remove-icon')
            .first()
            .click();

        // Add image to product
        cy.get('#files').attachFile('img/sw-test-image.png');

        cy.get('.sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
        cy.awaitAndCheckNotification('File has been saved.');

        cy.get('.sw-button-process').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw_theme_manager__confirm-save-text')
            .contains('Do you really want to save the changes? This will change the visualization of your shop.');
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.img-fluid').should('be.visible');
        cy.get('.img-fluid')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@base @content: saves theme primary color', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area');

        cy.get('.sw-colorpicker .sw-colorpicker__input').first().clear().typeAndCheck('#000');

        cy.get('.smart-bar__actions .sw-button-process.sw-button--primary').click();
        cy.get('.sw-modal .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-colorpicker .sw-colorpicker__input').first().should('have.value', '#000');

        cy.visit('/');
        cy.get('.header-cart-total')
            .should('have.css', 'color', 'rgb(0, 0, 0)');
    });

    it('@base @media @content: change theme logo image by sidebar', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .last()
            .get('.sw-theme-list-item__title')
            .contains('Shopware default theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.get('.sw-media-upload-v2__label')
            .contains('Desktop')
            .parent()
            .parent()
            .find('.sw-media-upload-v2__remove-icon')
            .click();

        cy.get('.sw-sidebar-navigation-item').click();

        cy.contains('.sw-media-base-item__name', 'demostore-logo.png')
            .parent()
            .parent()
            .find('.sw-context-button .sw-context-button__button')
            .click();

        cy.contains('.sw-context-menu-item__text', /Add to Desktop/)
            .click();

        cy.get('.sw-media-upload-v2')
            .first()
            .get('.sw-media-preview-v2__item')
            .should('have.attr', 'src')
            .and('match', /demostore-logo/);

        cy.get('.sw-button-process').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw_theme_manager__confirm-save-text')
            .contains('Do you really want to save the changes? This will change the visualization of your shop.');
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.img-fluid').should('be.visible');
        cy.get('.img-fluid')
            .should('have.attr', 'src')
            .and('match', /demostore-logo/);
    });
});
