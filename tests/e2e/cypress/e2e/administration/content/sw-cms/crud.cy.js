/**
 * @package content
 */
// / <reference types="Cypress" />

describe('CMS: Test crud operations of layouts', () => {
    beforeEach(() => {
        cy.createCmsFixture().then(() => {
            cy.viewport(1920, 1080);
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@base @content @package: create and read layout', { tags: ['pa-content-management'] }, () => {
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
        cy.get('#sw-field--page-name').typeAndCheck('Laidout');
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
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Laidout');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-cms-list-item--0 .sw-cms-list-item__title', 'Laidout');

        // Duplicate layout
        cy.get('.sw-cms-list-item--0')
            .find('.sw-cms-list-item__options.sw-context-button').click({ force: true });
        cy.get('.sw-cms-list-item__option-duplicate').click();
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-cms-list-item--0', 'Laidout - Copy').should('be.visible');
    });

    it('@base @content @package: update and read layout', { tags: ['pa-content-management'] }, () => {
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
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-detail__back-btn').click();
        cy.contains('.sw-cms-list-item--0 .sw-cms-list-item__title', 'Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');

        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');

        // Save layout
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.contains('.cms-block h2', 'Lorem Ipsum dolor sit amet');
    });

    it('@base @content @package: delete layout', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'delete',
        }).as('deleteData');

        cy.clickContextMenuItem(
            '.sw-cms-list-item__option-delete',
            '.sw-cms-list-item__options',
            '.sw-cms-list-item--0',
            '',
            true,
        );
        cy.contains('.sw_tree__confirm-delete-text',
            'Are you sure you really want to delete the layout "Vierte Wand"?');
        cy.get('.sw-button--danger').click();

        cy.wait('@deleteData')
            .its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title')
            .should('not.have.value', 'Vierte Wand');
    });
});
