/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('Category: Test ACL privileges', () => {
    beforeEach(() => {
        cy.createCmsFixture().then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });
    });

    it('@base @catalogue: can view shopping experiences listing', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'cms',
                role: 'viewer',
            },
        ]);

        cy.viewport(1920, 1080);
        cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-cms-list-item--0 > .sw-cms-list-item__info > .sw-cms-list-item__title',
            'Vierte Wand');
    });

    it('@catalogue: can view shopping experiences detail page', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'cms',
                role: 'viewer',
            },
        ]);

        cy.viewport(1920, 1080);
        cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.contains('.sw-cms-list-item--0 > .sw-cms-list-item__info > .sw-cms-list-item__title',
            'Vierte Wand');

        cy.get('.sw-cms-list-item--0 > .sw-cms-list-item__image')
            .click();

        // check if detail page works
        cy.contains('.sw-cms-detail__page-name', 'Vierte Wand');
    });

    it('@catalogue: can edit shopping experiences detail page', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'cms',
                role: 'viewer',
            },
            {
                key: 'cms',
                role: 'editor',
            },
        ]);

        cy.viewport(1920, 1080);
        cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH',
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(2)')
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Edit headline
        cy.get('.sw-text-editor__content-editor').should('be.visible');
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').clear();
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').type('Chocolate cake dragée');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();
        cy.contains('.sw-cms-list-item--0 .sw-cms-list-item__title', 'Vierte Wand');
    });

    it('@catalogue: can edit shopping experiences detail page', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.loginAsUserWithPermissions([
            {
                key: 'cms',
                role: 'viewer',
            },
            {
                key: 'cms',
                role: 'editor',
            },
            {
                key: 'cms',
                role: 'creator',
            },
        ]);

        cy.viewport(1920, 1080);
        cy.visit(`${Cypress.env('admin')}#/sw/cms/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST',
        }).as('saveData');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Landing page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.contains('.sw-cms-create-wizard__title', 'How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('input[placeholder*="Enter layout name"]').typeAndCheck('Laidout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-detail__back-btn').click();

        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Laidout');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-cms-list-item--0 .sw-cms-list-item__title', 'Laidout');
    });
});
