// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

describe('CMS: check validation of product list page', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@package @content: create product list page and try to save with deleted listing block', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page',
            method: 'post'
        }).as('saveData');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Listing page').click();
        cy.get('.sw-cms-create-wizard__title').contains('Which section would you like to start with?');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.get('.sw-cms-create-wizard__title').contains('What should your layout be called?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('Laidout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('not.be.visible');
        cy.get('.sw-cms-block-product-listing').should('be.visible');

        // Add simple text block
        cy.get('.sw_sidebar__navigation-list li').first().click();

        cy.get('.sw-cms-sidebar__section-setting')
            .first()
            .click();
        cy.get('.sw-collapse__content').should('be.visible');
        cy.get('.sw-cms-sidebar-section-settings__quickaction.is--danger').click();

        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            cy.awaitAndCheckNotification('Unable to save. Please add a product listing block or change the layout type.');
        });
    });
});
