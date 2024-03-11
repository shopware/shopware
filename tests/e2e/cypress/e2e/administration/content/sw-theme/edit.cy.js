/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Theme: Test common editing of theme', { tags: ['VUE3']}, () => {
    beforeEach(() => {
        cy.viewport(1920, 1080);
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/theme/manager/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@base @media @content: change theme logo image', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.get('.sw-inherit-wrapper__inheritance-label')
            .eq(15)
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
        cy.contains('.sw_theme_manager__confirm-save-text',
            'Do you really want to save the changes? This will change the appearance of your shops.');
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.img-fluid').should('be.visible');
        cy.get('.img-fluid')
            .should('have.attr', 'src')
            .and('match', /sw-test-image/);
    });

    it('@base @content: saves theme primary color', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
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

    it('@base @content: filter not allowed values', { tags: ['pa-sales-channels'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.get('.sw-theme-manager-detail__area');

        cy.get('.sw-field-id-sw-font-family-base input').first().clear().type('\'Inter\', sans-serif').blur()
            .should('have.value', '\'Inter\', sans-serif');

        cy.get('.sw-field-id-sw-font-family-base input').first().clear().type('\'Inter\', sans-serif`').blur()
            .should('have.value', '\'Inter\', sans-serif');

        cy.get('.sw-field-id-sw-font-family-base input').first().clear().type('\'Inter\', sans-serif´').blur()
            .should('have.value', '\'Inter\', sans-serif');
    });

    it('@base @media @content: change theme logo image by sidebar', { tags: ['pa-sales-channels', 'quarantined'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/theme/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-theme-list-item')
            .contains('.sw-theme-list-item__title', 'Shopware default theme')
            .click();

        cy.contains('.sw-card__title', 'Media').scrollIntoView();
        cy.contains('.sw-inherit-wrapper__inheritance-label', 'Desktop')
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
        cy.contains('.sw_theme_manager__confirm-save-text',
            'Do you really want to save the changes? This will change the appearance of your shops.');
        cy.get('.sw-modal__footer > .sw-button--primary').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.img-fluid').should('be.visible');
        cy.get('.img-fluid')
            .should('have.attr', 'src')
            .and('match', /demostore-logo/);
    });
});
