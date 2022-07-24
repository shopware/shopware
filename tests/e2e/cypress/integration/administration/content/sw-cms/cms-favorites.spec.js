// / <reference types="Cypress" />

describe('CMS: Check if block favorites open first, when configured', () => {
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

    it('@base @content: select block favorites and re-open editor to see effects', () => {
        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').should('have.value', 'text');
        cy.get('.sw-cms-sidebar__block-preview-with-actions .sw-button').first().click();

        // re open
        cy.get('.sw-cms-detail__back-btn').click()
        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').click();

        cy.get('#sw-field--currentBlockCategory').should('have.value', 'favorite');

        // unselect
        cy.get('.sw-cms-sidebar__block-preview-with-actions .sw-button').first().click();

        // re open
        cy.get('.sw-cms-detail__back-btn').click()
        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').click();

        cy.get('#sw-field--currentBlockCategory').should('have.value', 'text');
    });

    it('@base @content: select element favorites and re-open editor to see effects', () => {
        cy.get('.sw-cms-list-item--0').click();

        // Add a text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Text');
        cy.get('.sw-cms-preview-text').should('be.visible');
        cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');

        // open switch dialog
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot__element-action').click();

        // all (no favorites)
        cy.get('.sw-cms-slot__modal-container').find('.sw-sidebar-collapse__header').should('have.length', 1);

        // favorite
        cy.get('.element-selection__overlay-action-favorite').first().invoke('show');
        cy.get('.element-selection__overlay-action-favorite').first().should('be.visible');
        cy.get('.element-selection__overlay-action-favorite').first().click();

        // close switch dialog
        cy.get('.sw-modal__close').click();

        // open switch dialog
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot__element-action').click();

        // favorites + all
        cy.get('.sw-cms-slot__modal-container').find('.sw-sidebar-collapse__header').should('have.length', 2);

        // unfavorite
        cy.get('.sw-cms-slot__modal-container .sw-sidebar-collapse__header').first().scrollIntoView();
        cy.get('.element-selection__overlay-action-favorite').first().invoke('show');
        cy.get('.element-selection__overlay-action-favorite').first().should('be.visible');
        cy.get('.element-selection__overlay-action-favorite').first().click();

        // all (no favorites)
        cy.get('.sw-cms-slot__modal-container').find('.sw-sidebar-collapse__header').should('have.length', 1);
    });
});
