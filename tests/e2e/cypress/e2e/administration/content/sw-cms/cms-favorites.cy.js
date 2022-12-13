// / <reference types="Cypress" />

describe('CMS: Check if block favorites open first, when configured', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => cy.createCmsFixture())
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @content: select block favorites and re-open editor to see effects', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user-config*`,
            method: 'POST'
        }).as('createUserConfig');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').should('have.value', 'text');
        cy.get('.sw-cms-sidebar__block-preview-with-actions .sw-button').first().click();

        cy.wait('@createUserConfig').its('response.statusCode').should('equal', 204);

        cy.log('reopen');
        cy.get('.sw-cms-detail__back-btn').click()
        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').click();

        cy.get('#sw-field--currentBlockCategory').should('have.value', 'favorite');

        cy.log('unselect');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user-config/*`,
            method: 'PATCH'
        }).as('updateUserConfig');
        cy.get('.sw-cms-sidebar__block-preview-with-actions .sw-button').first().click();
        cy.wait('@updateUserConfig').its('response.statusCode').should('equal', 204);

        cy.log('reopen');
        cy.get('.sw-cms-detail__back-btn').click()
        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').click();

        cy.log('should not have any favorites');
        cy.get('#sw-field--currentBlockCategory').select('favorite');
        cy.get('.sw-cms-sidebar__block-selection .sw-empty-state').should('be.visible');
    });

    it('@base @content: select element favorites and re-open editor to see effects', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user-config*`,
            method: 'POST'
        }).as('createUserConfig');

        cy.get('.sw-cms-list-item--0').click();

        cy.log('Add a text block');
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('#sw-field--currentBlockCategory').select('Text');
        cy.get('.sw-cms-preview-text').should('be.visible');
        cy.get('.sw-cms-preview-text').dragTo('.sw-cms-section__empty-stage');

        cy.log('open switch dialog');
        cy.get('.sw-cms-block__config-overlay').invoke('show');
        cy.get('.sw-cms-block__config-overlay').should('be.visible');
        cy.get('.sw-cms-block__config-overlay').click();
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot__element-action').click();

        cy.log('all (no favorites)');
        cy.get('.sw-cms-slot__modal-container .sw-collapse').should('have.length', 1);

        cy.log('favorite');
        cy.get('.element-selection__overlay-action-favorite').first().invoke('show');
        cy.get('.element-selection__overlay-action-favorite').first().click();
        cy.wait('@createUserConfig').its('response.statusCode').should('equal', 204);

        cy.log('close switch dialog');
        cy.get('.sw-modal__close').click();

        cy.log('open switch dialog');
        cy.get('.sw-cms-block__config-overlay.is--active').should('be.visible');
        cy.get('.sw-cms-slot .sw-cms-slot__overlay').invoke('show');
        cy.get('.sw-cms-slot__element-action').click();

        cy.log('favorites + all');
        cy.get('.sw-cms-slot__modal-container .sw-collapse').should('have.length', 2);

        cy.log('unfavorite');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/user-config/*`,
            method: 'PATCH'
        }).as('updateUserConfig');
        cy.get('.sw-cms-slot__modal-container .sw-collapse').first().scrollIntoView({
            offset: { top: 0, left: 0 }
        });
        cy.get('.element-selection__overlay-action-favorite').first().invoke('show');
        cy.get('.element-selection__overlay-action-favorite').first().click();
        cy.wait('@updateUserConfig').its('response.statusCode').should('equal', 204);

        cy.log('all (no favorites)');
        cy.get('.sw-cms-slot__modal-container .sw-collapse').should('have.length', 1);
    });
});