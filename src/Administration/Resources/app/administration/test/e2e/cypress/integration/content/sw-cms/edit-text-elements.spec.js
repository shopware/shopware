// / <reference types="Cypress" />

describe('CMS: Check usage and editing of text elements', () => {
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

    it('@base @content: use text block with headline', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(2)')
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Edit headline
        cy.get('.sw-text-editor__content-editor').should('be.visible');
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').clear();
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').type('Chocolate cake dragée');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then(() => {
            cy.get('.sw-cms-detail__back-btn').click();
            cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');
        });

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block').contains('Chocolate cake dragée');
    });

    it('@base @content: edit text block settings', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(2)')
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');

        // Open block settings
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible').click();
        cy.get('.sw-cms-block-config').should('be.visible');
        cy.get('.sw-colorpicker__input').should('be.visible');

        // Change block background color and check for preview changes
        cy.get('.sw-colorpicker__input').type('#000000');
        cy.get('.sw-cms-block').should('have.css', 'background-color', 'rgb(0, 0, 0)');

        // Save
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData');

        // Check if settings are still reactive and change preview
        cy.get('.sw-colorpicker__input').clear().type('#FF0000');
        cy.get('.sw-cms-block').should('have.css', 'background-color', 'rgb(255, 0, 0)');

        // Save
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData');

        // Reload page and check that value persisted
        cy.reload();
        cy.get('.sw-cms-block').should('have.css', 'background-color', 'rgb(255, 0, 0)');
    });

    it('@content: use text block with three columns', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveCategory');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add text block with three columns
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(6)').scrollIntoView();
        cy.get('.sw-cms-sidebar__block-selection > div:nth-of-type(6)')
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor p').first().contains('Lorem ipsum dolor sit amet');

        // Edit text in each column
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').clear();
        cy.get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').type('Chocolate cake dragée jelly lemon drops pastry oat cake pastry candy jelly-o. Jujubes marshmallow chocolate bar cotton candy icing. Sugar plum jelly liquorice jelly beans. Ice cream sugar plum powder marzipan dessert danish bonbon.');
        cy.get('.sw-cms-slot:nth-of-type(2) .sw-text-editor__content-editor').clear();
        cy.get('.sw-cms-slot:nth-of-type(2) .sw-text-editor__content-editor').type('Croissant marshmallow topping jelly beans sesame snaps. Tart apple pie muffin oat cake danish caramels tart icing muffin. Cupcake cotton candy cookie bear claw.');
        cy.get('.sw-cms-slot:nth-of-type(3) .sw-text-editor__content-editor').clear();
        cy.get('.sw-cms-slot:nth-of-type(3) .sw-text-editor__content-editor').type('Macaroon cheesecake chocolate caramels sweet cupcake tart. Icing jelly topping gummies. Candy canes carrot cake gummies carrot cake powder oat cake.');

        // Save layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then(() => {
            cy.get('.sw-cms-detail__back-btn').click();
            cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');
        });

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block .col-md-4:nth-of-type(1) .cms-element-text').contains('Chocolate cake');
        cy.get('.cms-block .col-md-4:nth-of-type(2) .cms-element-text').contains('Croissant marshmallow');
        cy.get('.cms-block .col-md-4:nth-of-type(3) .cms-element-text').contains('Macaroon cheesecake');
    });
});
