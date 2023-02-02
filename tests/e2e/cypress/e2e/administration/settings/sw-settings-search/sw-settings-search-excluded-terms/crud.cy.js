// / <reference types="Cypress" />

import SettingsPageObject from '../../../../../support/pages/module/sw-settings.page-object';

describe('Product Search: Test crud operations', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: check pagination existence', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').contains('Excluded search terms');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms .sw-data-grid__pagination').should('be.visible');

        cy.get('.sw-settings-search-excluded-search-terms .sw-pagination__list-item').each(($el, index) => {
            cy.get('.sw-pagination__list-button').contains(index + 1).click();
            cy.get('.sw-settings-search-excluded-search-terms .sw-data-grid-skeleton').should('not.exist');
            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`).should('be.visible');
        });
    });

    it('@settings: create and update excluded terms', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').contains('Excluded search terms');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`)
            .invoke('text')
            .then((resultTextBefore) => {
                // create excluded terms
                cy.wrap([1, 2, 3, 4, 5]).each(($el, index) => {
                    cy.get('.sw-settings-search-excluded-search-terms')
                        .contains('.sw-settings-search-excluded-search-terms__insert-button', 'Exclude search term')
                        .click();
                    cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`)
                        .clearTypeAndCheck(index);
                    cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__inline-edit-save`)
                        .click();

                    cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`)
                        .invoke('text')
                        .should((resultTextAfter) => {
                            expect(resultTextBefore.trim()).to.not.equal(resultTextAfter.trim());
                        });

                    cy.wait('@saveData').its('response.statusCode').should('within', 200, 204);
                    cy.awaitAndCheckNotification('Excluded search term created.');
                    cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`)
                        .contains('.sw-data-grid__cell-value', index);
                })
                    .then(() => {
                    // update excluded terms
                        cy.wrap([1, 2, 3, 4, 5]).each(($el, index) => {
                            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--${index}`).dblclick();
                            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--${index} input[name=sw-field--currentValue]`)
                                .clearTypeAndCheck(`${index} edited`);
                            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--${index} .sw-data-grid__inline-edit-save`)
                                .click();

                            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--${index} .sw-data-grid__cell-value`)
                                .invoke('text')
                                .should((resultTextAfter) => {
                                    expect(resultTextBefore.trim()).to.not.equal(resultTextAfter.trim());
                                });

                            cy.wait('@saveData').its('response.statusCode').should('within', 200, 204);
                            cy.awaitAndCheckNotification('Excluded search term updated.');
                            cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--${index}`)
                                .contains('.sw-data-grid__cell-value', `${index} edited`);
                        });
                    });
            });
    });

    it('@settings: delete excluded terms', { tags: ['quarantined', 'pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/product-search-config/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').contains('Excluded search terms');

        // Single delete excluded term
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-context-button__button`)
            .should('be.visible');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-context-button__button`)
            .click();

        cy.get('.sw-context-menu-item.sw-context-menu-item--danger')
            .should('be.visible');
        cy.get('.sw-context-menu-item.sw-context-menu-item--danger')
            .click();

        cy.wait('@saveData').its('response.statusCode').should('within', 200, 204);
        cy.awaitAndCheckNotification('Excluded search term deleted.');

        // Bulk delete excluded term
        cy.get('.sw-settings-search-excluded-search-terms .sw-data-grid__cell--header.sw-data-grid__cell--selection input')
            .check();
        cy.get('.sw-settings-search-excluded-search-terms')
            .contains('.sw-data-grid__bulk-selected.sw-data-grid__bulk-selected-count', 10);
        cy.get('.sw-settings-search-excluded-search-terms')
            .contains('.sw-data-grid__bulk .sw-data-grid__bulk-selected.bulk-link button', 'Delete')
            .click();

        cy.wait('@saveData').its('response.statusCode').should('within', 200, 204);
        cy.awaitAndCheckNotification('Excluded search term deleted.');
    });

    it('@settings: search for excluded terms', { tags: ['pa-system-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.get('.sw-settings-search-excluded-search-terms').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms .sw-card__title').contains('Excluded search terms');

        cy.get('.sw-settings-search-excluded-search-terms')
            .contains('.sw-settings-search-excluded-search-terms__insert-button', 'Exclude search term')
            .click();
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`)
            .clearTypeAndCheck('Example');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__inline-edit-save`).click();

        cy.get('.sw-card-filter .sw-block-field__block input[type="text"]').type('Example');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0`)
            .contains('.sw-data-grid__cell-value', 'Example');
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--1 .sw-data-grid__cell-value`)
            .should('not.exist');
    });
});
