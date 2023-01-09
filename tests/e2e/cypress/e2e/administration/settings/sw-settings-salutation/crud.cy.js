// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Salutation: crud salutations', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: can create a new salutation', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();
        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/salutation`,
            method: 'POST',
        }).as('createSalutation');

        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // go to create salutation page
        cy.get('.sw-settings-salutation-list__create').click();

        // clear old data and type another one in salutationKey field
        cy.get('#sw-field--salutation-salutationKey')
            .clear()
            .type('Ms');

        // clear old data and type another one in displayName field
        cy.get('#sw-field--salutation-displayName')
            .clear()
            .type('Miss');

        // clear old data and type another one in letterName field
        cy.get('#sw-field--salutation-letterName')
            .clear()
            .type('Dear Miss');

        cy.get('.sw-settings-salutation-detail__save').click();

        // Verify creation
        cy.wait('@createSalutation').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Ms');

        // assert salutations list is exists and contains new salutation in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).contains('Ms').should('be.visible');
    });

    it('@settings: can edit a salutation', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/salutation/*`,
            method: 'PATCH',
        }).as('editSalutation');

        // go to salutation module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // click on the first element in grid
        cy.get(`${page.elements.dataGridRow}--0`).contains('mr').click();

        // clear old data and type another one in letterName field
        cy.get('#sw-field--salutation-letterName')
            .clear()
            .type('Dear Boss');

        // click save salutation button
        cy.get('.sw-settings-salutation-detail__save').click();

        // Verify creation
        cy.wait('@editSalutation').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Dear Boss');

        // assert salutations list is exists and contains salutation which was edited before in list
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0`).contains('Dear Boss').should('be.visible');
    });

    it('@settings: can delete a salutation', { tags: ['pa-system-settings', 'quarantined'] }, () => {
        const page = new SettingsPageObject();
        // Prepare api to delete salutation
        cy.intercept({
            url: `${Cypress.env('apiPath')}/salutation/*`,
            method: 'delete',
        }).as('deleteSalutation');

        // go to salutaion module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-settings-salutation').click();

        // wait for salutation list to load
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');

        // wait for salutation list to load
        cy.get(`${page.elements.salutationListContent}`).should('be.visible');

        // click on first element in grid
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('mr');
        cy.get('body').click(0, 0);
        cy.get('.sw-search-bar__results--v2').should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // assert that confirmation modal appears
        cy.get('.sw-modal__body').should('be.visible');
        cy.contains('.sw-modal__body', 'Are you sure you want to delete this item?');

        // do deleting action
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();


        // call api to delete the salutaion
        cy.wait('@deleteSalutation').its('response.statusCode').should('equal', 204);
    });
});
