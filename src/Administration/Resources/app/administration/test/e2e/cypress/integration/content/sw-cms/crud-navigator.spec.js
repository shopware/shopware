// / <reference types="Cypress" />

const uuid = require('uuid/v4');

describe('CMS: Test crud operations in the cms-sidebar', () => {
    let pageId, sectionId;
    let blockSelector;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            pageId = uuid().replace(/-/g, '');
            sectionId = uuid().replace(/-/g, '');
            blockSelector = `#page-${pageId} .sw-text-editor__content-editor`;

            return cy.fixture('cms-page-full');
        }).then((data) => {
            data.id = pageId;
            data.sections[0].id = sectionId;

            return cy.createCmsFixture(data);
        }).then(() => {
            cy.viewport(1920, 1080);
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/detail/${pageId}`);

            cy.intercept({
                url: `${Cypress.env('apiPath')}/cms-page/*`,
                method: 'PATCH'
            }).as('saveData');

            cy.intercept({
                url: `${Cypress.env('apiPath')}/search/cms-page`,
                method: 'POST'
            }).as('searchData');

            // Get into navigator
            cy.get('button[title="Navigator"]').click();
            cy.contains('.sw-sidebar-item__title', 'Navigator');
            cy.get('.sw-cms-sidebar__navigator-confirm-modal-confirm').click();
            cy.wait('@searchData').its('response.statusCode').should('equal', 200);
        });
    });

    it('@base @content: should ask to save automatically on entering the sidebar without further asking', () => {
        cy.get('button[title="Settings"]').click();
        cy.contains('.sw-sidebar-item__title', 'Settings');

        // Get into navigator and check the reminder checkbox
        cy.get('button[title="Navigator"]').click();
        cy.contains('.sw-sidebar-item__title', 'Navigator');
        cy.get('.sw-cms-sidebar__navigator-confirm-modal-reminder').click();
        cy.get('.sw-cms-sidebar__navigator-confirm-modal-confirm').click();
        cy.wait('@searchData').its('response.statusCode').should('equal', 200);

        // Swap sidebar content back and forth
        cy.get('button[title="Settings"]').click();
        cy.contains('.sw-sidebar-item__title', 'Settings');

        cy.get('button[title="Navigator"]').click();
        cy.contains('.sw-sidebar-item__title', 'Navigator');
        cy.wait('@searchData').its('response.statusCode').should('equal', 200);
    });

    it('@base @content: should move sections', () => {
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');

        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-move-down').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(blockSelector).eq(0).contains('Section 2 - Block C')
        cy.get(blockSelector).eq(2).contains('Section 1 - Block A')
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E')

        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-move-down').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(blockSelector).eq(0).contains('Section 2 - Block C')
        cy.get(blockSelector).eq(2).contains('Section 3 - Block E')
        cy.get(blockSelector).eq(4).contains('Section 1 - Block A')

        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-move-up').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(blockSelector).eq(0).contains('Section 2 - Block C')
        cy.get(blockSelector).eq(2).contains('Section 1 - Block A')
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E')

        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-move-up').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');
    });

    it('@base @content: should clone blocks', () => {
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 6);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(5).contains('Section 3 - Block F');

        // Clone a block
        cy.get('.navigator-element__action-duplicate').eq(0).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 7);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(3).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(5).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(6).contains('Section 3 - Block F');

        cy.get('.navigator-element__action-duplicate').eq(5).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Clone another block
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 8);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(3).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(5).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(6).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(7).contains('Section 3 - Block F');
    });

    it('@base @content: should clone sections and blocks', () => {
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 6);
        // Section 1
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        // Section 2 + 3
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');

        // Clone a section
        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-duplicate').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 8);
        // Section 1
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        // Section 1 (First Clone)
        cy.get(blockSelector).eq(2).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(3).contains('Section 1 - Block B');
        // Section 2 + 3
        cy.get(blockSelector).eq(4).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(6).contains('Section 3 - Block E');

        // Clone a block
        cy.get('.navigator-element__action-duplicate').eq(1).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 9);
        // Section 1
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(2).contains('Section 1 - Block B');
        // Section 1 (First Clone)
        cy.get(blockSelector).eq(3).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(4).contains('Section 1 - Block B');
        // Section 2 + 3
        cy.get(blockSelector).eq(5).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(7).contains('Section 3 - Block E');

        // Clone the section with the cloned block
        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-duplicate').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 12);
        // Section 1
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(2).contains('Section 1 - Block B');
        // Section 1 (Second Clone)
        cy.get(blockSelector).eq(3).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(4).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(5).contains('Section 1 - Block B');
        // Section 1 (First Clone)
        cy.get(blockSelector).eq(6).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(7).contains('Section 1 - Block B');
        // Section 2 + 3
        cy.get(blockSelector).eq(8).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(10).contains('Section 3 - Block E');
    });

    it('@base @content: should delete blocks', () => {
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 6);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(3).contains('Section 2 - Block D');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(5).contains('Section 3 - Block F');

        // Clone a block
        cy.get('.navigator-element__action-delete').eq(0).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 5);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(1).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block D');
        cy.get(blockSelector).eq(3).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block F');

        cy.get('.navigator-element__action-delete').eq(3).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Clone another block
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 4);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(1).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block D');
        cy.get(blockSelector).eq(3).contains('Section 3 - Block F');
    });

    it('@base @content: should delete sections', () => {
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 6);
        cy.get(blockSelector).eq(0).contains('Section 1 - Block A');
        cy.get(blockSelector).eq(1).contains('Section 1 - Block B');
        cy.get(blockSelector).eq(2).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(3).contains('Section 2 - Block D');
        cy.get(blockSelector).eq(4).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(5).contains('Section 3 - Block F');

        cy.get(`#sw-cms-sidebar__section-${sectionId} .sw-context-button__button`).click();
        cy.get('.sw-cms-sidebar__navigator-section-delete').click();

        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-cms-sidebar__navigator-element').should('have.length', 4);
        cy.get(blockSelector).eq(0).contains('Section 2 - Block C');
        cy.get(blockSelector).eq(1).contains('Section 2 - Block D');
        cy.get(blockSelector).eq(2).contains('Section 3 - Block E');
        cy.get(blockSelector).eq(3).contains('Section 3 - Block F');
    });
});
