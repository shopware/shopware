// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Product Search: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/search/index`);
            });
    });

    // TODO: NEXT-15722 - Fails randomly on CI, but cannot reproduce
    it.skip('@settings: Pagination for product search config excluded terms', () => {
        const page = new SettingsPageObject();

        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).should('exist');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__pagination').should('exist');

        cy.get('.sw-settings-search-excluded-search-terms ' +
            ':nth-child(2) > .sw-pagination__list-button').click();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid-skeleton').should('not.exist');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).should('exist');
    });

    // TODO: NEXT-15722 - Fails randomly on CI, but cannot reproduce
    it.skip('@settings: create and update config for product search config excluded terms', () => {
        const page = new SettingsPageObject();
        cy.server();

        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-settings-search-excluded-search-terms > .sw-card__title').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms > .sw-card__title').should('be.visible');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`)
            .invoke('text')
            .then((resultTextBefore) => {
                // Add new excluded term
                cy.get('.sw-settings-search-excluded-search-terms__insert-button').should('be.visible');
                cy.get('.sw-settings-search-excluded-search-terms__insert-button').click();

                cy.get('.sw-settings-search-excluded-search-terms ' +
                    `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`)
                    .invoke('text')
                    .should((resultTextAfter) => {
                        expect(resultTextBefore).to.not.equal(resultTextAfter);
                    });

                cy.get('.sw-settings-search-excluded-search-terms ' +
                    `${page.elements.dataGridRow}--0`).dblclick();

                cy.get('.sw-settings-search-excluded-search-terms ' +
                    '.sw-data-grid__row.sw-data-grid__row--0 input[name=sw-field--currentValue]')
                    .clearTypeAndCheck('example');

                // Cancel add new excluded term
                cy.get('.sw-settings-search-excluded-search-terms ' +
                    `${page.elements.dataGridRow}--0 .sw-button.sw-data-grid__inline-edit-cancel`).click();
                cy.get('.sw-settings-search-excluded-search-terms ' +
                    `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`).invoke('text')
                    .should((resultTextAfter) => {
                        expect(resultTextBefore).to.equal(resultTextAfter);
                    });
            });


        cy.get('.sw-settings-search-excluded-search-terms__insert-button')
            .should('be.visible');

        cy.get('.sw-settings-search-excluded-search-terms__insert-button')
            .click();

        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 input[name=sw-field--currentValue]`).type('example');

        // Submit add new excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-button.sw-data-grid__inline-edit-save`).click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term created.');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`).contains('example');
    });

    // TODO: NEXT-15722 - Fails randomly on CI, but cannot reproduce
    it.skip('@settings: search term for product search config excluded terms', () => {
        const page = new SettingsPageObject();

        const searchTerm = 'example';
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-settings-search-excluded-search-terms__insert-button').click();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0`).dblclick();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__row.sw-data-grid__row--0 input[name=sw-field--currentValue]')
            .clearTypeAndCheck(searchTerm);
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-button.sw-data-grid__inline-edit-save`).click();

        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--1 .sw-data-grid__cell-value`).should('exist');
        cy.get('.sw-card-filter .sw-block-field__block input[type="text"]').type(searchTerm);
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--0 .sw-data-grid__cell-value`).should('contain', searchTerm);
        cy.get('.sw-settings-search-excluded-search-terms ' +
            `${page.elements.dataGridRow}--1 .sw-data-grid__cell-value`).should('not.exist');
    });

    // TODO: NEXT-15722 - Fails randomly on CI, but cannot reproduce
    it.skip('@settings: delete config for product search config excluded terms', () => {
        const page = new SettingsPageObject();
        cy.server();

        cy.route({
            url: '/api/product-search-config/*',
            method: 'patch'
        }).as('saveData');


        cy.get('.sw-settings-search-excluded-search-terms > .sw-card__title').scrollIntoView();
        cy.get('.sw-settings-search-excluded-search-terms > .sw-card__title').should('be.visible');

        // Single delete excluded term
        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-context-button__button`)
            .should('be.visible');

        cy.get(`.sw-settings-search-excluded-search-terms ${page.elements.dataGridRow}--0 .sw-context-button__button`)
            .click();

        cy.get('.sw-context-menu-item.sw-context-menu-item--danger').should('be.visible');
        cy.get('.sw-context-menu-item.sw-context-menu-item--danger').click();

        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
        cy.awaitAndCheckNotification('Excluded search term deleted.');

        // Bulk delete excluded term
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__row .sw-data-grid__cell.sw-data-grid__cell--header.sw-data-grid__cell--selection input')
            .check();
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk-selected.sw-data-grid__bulk-selected-count').contains(10);
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk .sw-data-grid__bulk-selected.bulk-link button').should('be.visible');
        cy.get('.sw-settings-search-excluded-search-terms ' +
            '.sw-data-grid__bulk .sw-data-grid__bulk-selected.bulk-link button').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
