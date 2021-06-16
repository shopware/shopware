// / <reference types="Cypress" />

describe('CMS: Visual tests', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createCmsFixture();
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: check appearance of cms layout workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        cy.route({
            url: `${Cypress.env('apiPath')}/category/*`,
            method: 'patch'
        }).as('saveCategory');

        cy.clickMainMenuItem({
            targetPath: '#/sw/cms/index',
            mainMenuId: 'sw-content',
            subMenuId: 'sw-cms'
        });

        // Take snapshot for visual testing
        cy.get('.sw-cms-list-item--0').should('be.visible');
        cy.takeSnapshot('[CMS] Listing - Layouts', '.sw-cms-list');

        cy.get('.sw-cms-list-item--0').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Save new page layout
        cy.get('.sw-cms-detail__save-action').click();
        cy.wait('@saveData').then((xhr) => {
            cy.get('.icon--small-default-checkmark-line-medium').should('be.visible');
            expect(xhr).to.have.property('status', 204);
            cy.get('.icon--small-default-checkmark-line-medium').should('not.exist');

            // Take snapshot for visual testing
            cy.get('.sw-loader').should('not.exist');
            cy.contains('Vierte Wand').click();
            cy.get('.sw-tooltip').should('not.exist');
            cy.takeSnapshot('[CMS] Detail, Layout with text', '.sw-cms-detail__stage');
        });
        cy.get('.sw-cms-detail__back-btn').click();
        cy.get('.sw-cms-list-item--0 .sw-cms-list-item__title').contains('Vierte Wand');

        // Assign layout to root category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');

        cy.get('.sw-cms-layout-modal__content-item--0 .sw-field--checkbox').click();
        cy.get('.sw-modal .sw-button--primary').click();
        cy.get('.sw-card.sw-category-layout-card .sw-category-layout-card__desc-headline').contains('Vierte Wand');

        // Save layout
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@saveCategory').then((response) => {
            expect(response).to.have.property('status', 204);
        });

        // Verify layout in Storefront
        cy.visit('/');
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');
        cy.takeSnapshot('[CMS] Layout in Storefront', '.cms-block');
    });
});
