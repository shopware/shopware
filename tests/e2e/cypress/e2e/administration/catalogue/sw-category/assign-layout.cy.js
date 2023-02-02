/**
 * @package content
 */
// <reference types="Cypress" />

describe('Category: Create several categories', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
    });

    it('should be able to assign layouts with the grid view', { tags: ['pa-content-management'] }, () => {
        cy.log('Go to Category Layout selection screen');
        cy.get('.tree-link:nth(0)').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action > .sw-button__content').click();

        cy.log('Sort by Name, ascending');
        cy.get('.sw-cms-layout-modal__actions-sorting select').select('Name, ascending');

        cy.log('Check that sorting got applied for list view aswell and switch back to grid view afterwards');
        cy.get('.sw-cms-layout-modal__actions-mode').click();
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content .icon--regular-chevron-up-xxs').should('exist');
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--name', 'Default listing layout');
        cy.get('.sw-cms-layout-modal__actions-mode').click();

        cy.log('Select Default listing layout, which should still be at the top in the Name, ascending sorting');
        cy.contains('.sw-cms-list-item__info:nth(0) > .sw-cms-list-item__title', 'Default listing layout');
        cy.get('.sw-cms-layout-modal__content-checkbox:nth(0)').click();

        cy.log('Save selection');
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.contains('.sw-category-layout-card > .sw-card__content .sw-cms-list-item__title', 'Default listing layout');
    });

    it('should be able to assign layouts with the list view ', { tags: ['pa-content-management'] }, () => {
        cy.log('Go to Category Layout selection screen');
        cy.get('.tree-link:nth(0)').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action > .sw-button__content').click();

        cy.log('Switch to List view');
        cy.get('.sw-cms-layout-modal__actions-mode').click();

        cy.log('Sort by Name, ascending');
        cy.get('.sw-cms-layout-modal__actions-sorting select').select('Name, ascending');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content .icon--regular-chevron-up-xxs').should('exist');

        cy.log('Sort in Grid by Name, descending');
        cy.get('.sw-data-grid__cell--selection:nth(0) input').click();
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click();
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content .icon--regular-chevron-down-xxs').should('exist');
        cy.get('.sw-cms-layout-modal__actions-sorting select').should('have.value', 'name:DESC');

        cy.log('Select the Terms of service Layout, which should be on top in the sorting by Name, descending');
        cy.contains('.sw-data-grid__row--0 > .sw-data-grid__cell--name', 'Terms of service');
        cy.get('.sw-data-grid__row--0 > .sw-data-grid__cell--selection input').click();

        cy.log('Check sorting and selection in the grid view and then switch back to list view');
        cy.get('.sw-cms-layout-modal__actions-mode').click();
        cy.get('.sw-cms-layout-modal__content-checkbox:nth(0) input').should('be.checked');
        cy.get('.sw-cms-layout-modal__actions-sorting select').should('have.value', 'name:DESC');
        cy.get('.sw-cms-layout-modal__actions-mode').click();

        cy.log('check that sorting is still applied after switching back');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content .icon--regular-chevron-down-xxs').should('exist');
        cy.get('.sw-cms-layout-modal__actions-sorting select').should('have.value', 'name:DESC');

        cy.log('Save selection');
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.contains('.sw-category-layout-card > .sw-card__content .sw-cms-list-item__title', 'Terms of service');
    });
});
