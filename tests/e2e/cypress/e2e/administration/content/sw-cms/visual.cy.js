/// <reference types="Cypress" />
/**
 * @package buyers-experience
 */

describe('CMS: Visual tests', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.createCmsFixture().then(() => {
            cy.viewport(1920, 1080);
            cy.openInitialPage(Cypress.env('admin'));
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it.skip('@visual: check appearance of cms layout workflow', { tags: ['pa-content-management', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'PATCH',
        }).as('saveCategory');

        cy.clickMainMenuItem({
            targetPath: '#/sw/cms/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-cms',
        });
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Take snapshot for visual testing
        cy.get('.sw-cms-list-item--0').should('be.visible');
        cy.get('.sw-skeleton__gallery').should('not.exist');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/cms-page`,
            method: 'POST',
        }).as('siteLoaded');

        cy.get('select#sortType').select('name:DESC').should('contain.text', 'desc');
        cy.get('.sw-skeleton__gallery').should('exist');

        cy.wait('@siteLoaded')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-skeleton__gallery').should('not.exist');
        cy.get('.sw-cms-list-item--0').should('be.visible');
        cy.get('.sw-cms-list-item--1').should('be.visible');
        cy.get('.sw-cms-list-item__title').should('be.visible');
        cy.get('.sw-pagination__list-item .is-active').click();
        cy.get('.sw-skeleton__gallery').should('not.exist');
        cy.get('.sw-cms-list-item--0').should('be.visible');
        cy.get('.sw-cms-list-item--1').should('be.visible');
        cy.get('.sw-cms-list-item__title').should('be.visible');
        cy.get('.sw-cms-list-item--1 .sw-cms-list-item__title').should('contain', 'Terms');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[CMS] Listing - Layouts', '.sw-cms-list', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

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
        cy.get('.icon--regular-checkmark-xs').should('be.visible');
        cy.get('.icon--regular-checkmark-xs').should('not.exist');

        // Take snapshot for visual testing
        cy.get('.sw-loader').should('not.exist');
        cy.contains('Vierte Wand').click();
        cy.get('.sw-tooltip').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[CMS] Detail, Layout with text', '.sw-cms-detail__stage', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
        cy.get('.sw-cms-detail__back-btn').click();
        cy.contains('.sw-cms-list-item--0 .sw-cms-list-item__title', 'Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');

        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.contains('.sw-category-layout-card .sw-category-layout-card__desc-headline', 'Vierte Wand');

        // Save layout
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 204);

        // Verify layout in Storefront
        cy.visit('/');
        cy.contains('.cms-block h2', 'Lorem Ipsum dolor sit amet');
        cy.takeSnapshot('[CMS] Layout in Storefront', '.cms-block');
    });
});
