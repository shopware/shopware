// / <reference types="Cypress" />

describe('CMS: check validation of product list page', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@content: create product list page and try to save with deleted listing block', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST'
        }).as('saveData');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Listing page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.contains('.sw-cms-create-wizard__title', 'How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('Laidout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('not.exist');
        cy.get('.sw-cms-block-product-listing').should('be.visible');

        // Add simple text block
        cy.get('.sw_sidebar__navigation-list li').first().click();

        cy.get('.sw-cms-section')
            .first()
            .get('.sw-cms-section__actions .sw-cms-section-select')
            .click();
        cy.get('.sw-cms-sidebar__section-settings').should('be.visible');
        cy.get('.sw-collapse__content').should('be.visible');
        cy.get('.sw-cms-section-config__quickaction.is--danger').click();
        cy.get('.sw-cms-detail__empty-stage-content').should('be.visible');
        cy.get('.sw-cms-detail__save-action').click();

        cy.awaitAndCheckNotification('Errors occured. Please check the error list in the editor.');
        cy.contains('.sw-alert__message', 'This listing page does not contain a product listing block.');
    });
});
