// / <reference types="Cypress" />

describe('CMS: Check order of slots throughout layout edits', () => {
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

    it('@base @content: create two column texts and test order', () => {
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

        // Add two column text blocks
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Text');
        cy.get('.sw-cms-preview-text-two-column').should('be.visible');
        cy.get('.sw-cms-preview-text-two-column').dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-preview-text-two-column').dragTo('.sw-cms-stage-add-block:last-of-type');
        cy.get('.sw-cms-preview-text-two-column').dragTo('.sw-cms-stage-add-block:last-of-type');
        cy.get('.sw-cms-preview-text-two-column').dragTo('.sw-cms-stage-add-block:last-of-type');

        // Add unique text to columns
        cy.get('.sw-cms-block').eq(0).find('.sw-cms-slot').eq(0).find('.sw-text-editor__content-editor')
            .clear().type('1st');
        cy.get('.sw-cms-block').eq(0).find('.sw-cms-slot').eq(1).find('.sw-text-editor__content-editor')
            .clear().type('2nd');
        cy.get('.sw-cms-block').eq(1).find('.sw-cms-slot').eq(0).find('.sw-text-editor__content-editor')
            .clear().type('3rd');
        cy.get('.sw-cms-block').eq(1).find('.sw-cms-slot').eq(1).find('.sw-text-editor__content-editor')
            .clear().type('4th');
        cy.get('.sw-cms-block').eq(2).find('.sw-cms-slot').eq(0).find('.sw-text-editor__content-editor')
            .clear().type('5th');
        cy.get('.sw-cms-block').eq(2).find('.sw-cms-slot').eq(1).find('.sw-text-editor__content-editor')
            .clear().type('6th');
        cy.get('.sw-cms-block').eq(3).find('.sw-cms-slot').eq(0).find('.sw-text-editor__content-editor')
            .clear().type('7th');
        cy.get('.sw-cms-block').eq(3).find('.sw-cms-slot').eq(1).find('.sw-text-editor__content-editor')
            .clear().type('8th');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            cy.get('.sw-cms-detail__back-btn').click();
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

        // Check if order of columns is correct
        cy.get('.sw-cms-mapping-field').eq(0).contains('1st').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(0).find('.sw-text-editor__content-editor').clear().type('First');
        cy.get('.sw-cms-mapping-field').eq(1).contains('2nd').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(1).find('.sw-text-editor__content-editor').clear().type('Second');
        cy.get('.sw-cms-mapping-field').eq(2).contains('3rd').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(2).find('.sw-text-editor__content-editor').clear().type('Third');
        cy.get('.sw-cms-mapping-field').eq(3).contains('4th').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(3).find('.sw-text-editor__content-editor').clear().type('Fourth');
        cy.get('.sw-cms-mapping-field').eq(4).contains('5th').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(4).find('.sw-text-editor__content-editor').clear().type('Fifth');
        cy.get('.sw-cms-mapping-field').eq(5).contains('6th').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(5).find('.sw-text-editor__content-editor').clear().type('Sixth');
        cy.get('.sw-cms-mapping-field').eq(6).contains('7th').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(6).find('.sw-text-editor__content-editor').clear().type('Seventh');
        cy.get('.sw-cms-mapping-field').eq(7).contains('8th').scrollIntoView().should('be.visible');
        cy.get('.sw-cms-mapping-field').eq(7).find('.sw-text-editor__content-editor').clear().type('Eigth');

        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');

        cy.get('.cms-block.pos-0').find('.cms-element-text').eq(0).contains('First').should('be.visible');
        cy.get('.cms-block.pos-0').find('.cms-element-text').eq(1).contains('Second').should('be.visible');
        cy.get('.cms-block.pos-1').find('.cms-element-text').eq(0).contains('Third').should('be.visible');
        cy.get('.cms-block.pos-1').find('.cms-element-text').eq(1).contains('Fourth').should('be.visible');
        cy.get('.cms-block.pos-2').find('.cms-element-text').eq(0).contains('Fifth').should('be.visible');
        cy.get('.cms-block.pos-2').find('.cms-element-text').eq(1).contains('Sixth').should('be.visible');
        cy.get('.cms-block.pos-3').find('.cms-element-text').eq(0).contains('Seventh').should('be.visible');
        cy.get('.cms-block.pos-3').find('.cms-element-text').eq(1).contains('Eigth').should('be.visible');
    });
});
