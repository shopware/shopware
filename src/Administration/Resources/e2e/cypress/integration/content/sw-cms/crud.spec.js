// / <reference types="Cypress" />

describe('CMS: Test crud operations of layouts', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('cms-page');
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@package @content: create and read layout', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page',
            method: 'post'
        }).as('saveData');

        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');

        // Fill in basic data
        cy.get('#sw-field--page-name').type('Laid out');
        cy.get('#sw-field--page-type').select('Landing page');

        // Add simple text block
        cy.contains('Add a block').click();
        cy.get('.sw-cms-detail__block-preview')
            .first()
            .dragTo('.sw-cms-detail__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Laid out');
    });

    it('@package @content: update and read layout', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-cms-list-item--0').click();

        // Add simple text block
        cy.contains('Add a block').click();
        cy.get('.sw-cms-detail__block-preview')
            .first()
            .dragTo('.sw-cms-detail__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-tree-item__element').contains('Catalogue #1').click();
        cy.get('.sw-category-detail-base__layout').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-category-detail-base__layout-preview .sw-cms-list-item__title').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');

    });

    it('@package @content: delete layout', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page/*',
            method: 'delete'
        }).as('deleteData');

        cy.clickContextMenuItem('.sw-cms-list-item__option-delete', '.sw-cms-list-item__options', '.sw-cms-list-item--0');
        cy.get('.sw_tree__confirm-delete-text')
            .contains('Are you sure you really want to delete the layout "Vierte Wand"?');
        cy.get('.sw-button--danger').click();

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title')
            .should('not.have.value', 'Vierte Wand');
    });
});
