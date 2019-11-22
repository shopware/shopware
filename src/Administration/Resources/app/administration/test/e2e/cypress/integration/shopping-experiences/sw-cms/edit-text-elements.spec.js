// / <reference types="Cypress" />

import MediaPageObject from '../../../support/pages/module/sw-media.page-object';

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

    it('@package @content: use text block with headline', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page/*',
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: '/api/v1/category/*',
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
        cy.get('.sw-text-editor__content-editor h2')
            .then($target => {
                let coords = $target[0].getBoundingClientRect();

                cy.get('.sw-text-editor__content-editor h2').click();
                cy.get('.sw-text-editor__content-editor h2').type('{uparrow}{uparrow}{uparrow}{uparrow}');
                cy.get('.sw-text-editor__content-editor h2').type('Unterbrechung');
                cy.get('.sw-text-editor__content-editor h2').type('{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}{del}');
                cy.get('.sw-text-editor__content-editor h2').type('{downarrow}{downarrow}');
                cy.get('.sw-text-editor__content-editor h2').type('\nHerr von Ribbeck auf Ribbeck im Havelland,\n' +
                    'Ein Birnbaum in seinem Garten stand,\n' +
                    'Und kam die goldene Herbsteszeit...\n');
            });

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then(() => {
            cy.get('.sw-cms-detail__back-btn').click();
            cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');
        });

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-tree-item__element').contains('Catalogue #1').click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-cms-list-item__title').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204)
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block h2').contains('Unterbrechung');
        cy.get('.cms-block p:nth-of-type(2)').contains('Herr von Ribbeck auf Ribbeck im Havelland,');
    });

    it('@package @content: use text block with three columns', () => {
        cy.server();
        cy.route({
            url: '/api/v1/cms-page/*',
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: '/api/v1/category/*',
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
        cy.get('.sw-cms-slot:nth-of-type(1) p').clear();
        cy.get('.sw-cms-slot:nth-of-type(1) p').type('Chocolate cake dragée jelly lemon drops pastry oat cake pastry candy jelly-o. Jujubes marshmallow chocolate bar cotton candy icing. Sugar plum jelly liquorice jelly beans. Ice cream sugar plum powder marzipan dessert danish bonbon.');
        cy.get('.sw-cms-slot:nth-of-type(2) p').clear();
        cy.get('.sw-cms-slot:nth-of-type(2) p').type('Croissant marshmallow topping jelly beans sesame snaps. Tart apple pie muffin oat cake danish caramels tart icing muffin. Cupcake cotton candy cookie bear claw.');
        cy.get('.sw-cms-slot:nth-of-type(3) p').clear();
        cy.get('.sw-cms-slot:nth-of-type(3) p').type('Macaroon cheesecake chocolate caramels sweet cupcake tart. Icing jelly topping gummies. Candy canes carrot cake gummies carrot cake powder oat cake.');

        // Save layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then(() => {
            cy.get('.sw-cms-detail__back-btn').click();
            cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');
        });

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-tree-item__element').contains('Catalogue #1').click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-cms-list-item__title').contains('Vierte Wand');
        cy.get('.sw-category-detail__save-action').click();

        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block .col-md-4:nth-of-type(1) p').contains('Chocolate cake');
        cy.get('.cms-block .col-md-4:nth-of-type(2) p').contains('Croissant marshmallow');
        cy.get('.cms-block .col-md-4:nth-of-type(3) p').contains('Macaroon cheesecake');
    });
});
