// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Test crud operations', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_8225');
    });

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        });
    });

    it('@settings: Create and read flow', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post'
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify successful save
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.smart-bar__header h2').contains('Order placed v1');

        // Verify created element
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed v1');
    });

    it('@settings: Try to create flow with empty required fields', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'post'
        }).as('saveEmptyData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.get('.smart-bar__header h2').contains('New flow');

        // Click save without entering any data
        cy.get('.sw-flow-detail__save').click();

        cy.awaitAndCheckNotification('Please choose trigger event before saving.');

        cy.get('.sw-flow-detail__tab-flow').click();

        // Check if empty required fields have error messages
        cy.get('.sw-flow-trigger__search-field .sw-field__error')
            .should('be.visible')
            .should('contain', 'This field must not be empty.');

        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        cy.get('.sw-flow-trigger__search-field .sw-field__error')
            .should('not.exist');

        cy.get('.sw-flow-detail__tab-general').click();

        // Click save without entering any data
        cy.get('.sw-flow-detail__save').click();

        // Verify 400 Bad request
        cy.wait('@saveEmptyData').then((xhr) => {
            expect(xhr).to.have.property('status', 400);
        });

        // Check if empty required fields have error messages
        cy.get('.sw-flow-detail-general__general-name .sw-field__error')
            .should('be.visible')
            .should('contain', 'This field must not be empty.');

        cy.awaitAndCheckNotification('The flow could not be saved.');
    });

    // NEXT-17407 - this test does not work and needs to be fixed
    it.skip('@settings: Update and read flow', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'patch'
        }).as('updateData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed');

        cy.clickContextMenuItem(
            '.sw-flow-list__item-edit',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Verify correct detail page
        cy.get('.smart-bar__header h2').contains('Order placed');


        cy.get('#sw-field--flow-name').clearTypeAndCheck('Order placed v2');
        cy.get('.sw-flow-detail__tab-flow').click();

        cy.get('.sw-flow-sequence-action__add-button').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Generate document', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-generate-document-modal').should('be.visible');

        cy.get('.sw-flow-generate-document-modal__type-select')
            .typeSingleSelect('Invoice', '.sw-flow-generate-document-modal__type-select');

        cy.get('.sw-flow-generate-document-modal__save-button').click();
        cy.get('.sw-flow-generate-document-modal').should('not.exist');
        cy.get('li.sw-flow-sequence-action__action-item').should('have.length', 2);

        cy.get('.sw-flow-detail__save').click();
        cy.wait('@updateData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify updated element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed v2');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed v2');
    });

    it('@settings: Delete flow', () => {
        const page = new SettingsPageObject();

        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed');

        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Order placed').should('not.exist');
    });
});
