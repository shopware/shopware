// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Test crud operations', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
    });

    it('@settings: Create and read flow', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-results').should('be.visible');
        cy.get('.sw-flow-trigger__search-results').eq(0).click();

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify successful save
        cy.contains('.smart-bar__header h2', 'Order placed v1');

        // Verify created element
        cy.get(page.elements.smartBarBack).click({force: true});
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed v1');
    });

    it('@settings: Try to create flow with empty required fields', { tags: ['pa-business-ops'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST',
        }).as('saveEmptyData');

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Click save without entering any data
        cy.get('.sw-flow-detail__save').click();

        // Verify 400 Bad request
        cy.wait('@saveEmptyData').its('response.statusCode').should('equal', 400);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Automatically jump to tab Flow and check if name field has error message
        cy.get('.sw-flow-detail-general__general-name .sw-field__error')
            .should('be.visible')
            .should('contain', 'This field must not be empty.');

        cy.awaitAndCheckNotification('Flow could not be saved.');

        cy.get('.sw-flow-detail-general__general-name').type('Order placed');
        cy.get('.sw-flow-detail-general__general-name .sw-field__error')
            .should('not.exist');

        cy.get('.sw-flow-detail__save').click();

        // Verify 400 Bad request
        cy.wait('@saveEmptyData').its('response.statusCode').should('equal', 400);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Check if trigger event field ha error message
        cy.get('.sw-flow-trigger__search-field .sw-field__error')
            .should('be.visible')
            .should('contain', 'This field must not be empty.');

        cy.awaitAndCheckNotification('Flow could not be saved.');
    });

    it('@settings: Update and read flow', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'PATCH',
        }).as('updateData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/flow`,
            method: 'POST',
        }).as('getFlow');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        cy.clickContextMenuItem(
            '.sw-flow-list__item-edit',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
            'Edit',
            true,
        );

        cy.get('#sw-field--flow-name').clearTypeAndCheck('Order placed v2');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-flow-detail__tab-flow').click();

        cy.get('li.sw-flow-sequence-action__action-item').then(($listing) => {
            const listLength = $listing.length;

            cy.get('.sw-flow-sequence-action__selection-action')
                .typeSingleSelect('Generate document', '.sw-flow-sequence-action__selection-action');

            cy.get('.sw-flow-generate-document-modal').should('be.visible');

            cy.get('.sw-flow-generate-document-modal__type-multi-select').typeMultiSelectAndCheck('Invoice');

            cy.get('.sw-flow-generate-document-modal__save-button').click();
            cy.get('.sw-flow-generate-document-modal').should('not.exist');
            cy.get('li.sw-flow-sequence-action__action-item').should('have.length', listLength + 1);

            cy.get('.sw-flow-detail__save').click();
            cy.wait('@updateData').its('response.statusCode').should('equal', 204);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.wait('@getFlow').its('response.statusCode').should('equal', 200);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            // Verify updated element
            cy.get(page.elements.smartBarBack).click({ force: true });
            cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed v2');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
                .contains('Order placed v2');
        });
    });

    it('@settings: Duplicate a flow', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/flow/*`,
            method: 'POST',
        }).as('duplicateData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).then(($row) => {
            const firstFlowName = $row.text().trim();

            cy.clickContextMenuItem(
                '.sw-flow-list__item-duplicate',
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`,
                'Duplicate',
                true,
            );

            cy.wait('@duplicateData').its('response.statusCode').should('equal', 200);

            // Verify correct detail page
            cy.contains('.smart-bar__header h2', `${firstFlowName} - Copy`);

            // Verify duplicated element
            cy.get(page.elements.smartBarBack).click();
            cy.get('input.sw-search-bar__input').typeAndCheckSearchField(`${firstFlowName} - Copy`);
            cy.get('.sw-data-grid-skeleton').should('not.exist');
            cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
                .contains(`${firstFlowName} - Copy`);
        });
    });

    it('@settings: Delete flow', { tags: ['pa-business-ops'] }, () => {
        const page = new SettingsPageObject();

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'DELETE',
        }).as('deleteData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).then(($row) => {
            const firstFlowName = $row.text().trim();

            cy.clickContextMenuItem(
                `${page.elements.contextMenu}-item--danger`,
                page.elements.contextMenuButton,
                `${page.elements.dataGridRow}--0`,
                'Delete',
                true,
            );

            cy.contains('.sw-modal__body', 'If you delete this flow, no more actions will be performed for the trigger. Are you sure you want to delete this flow?');
            cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
            cy.get(page.elements.modal).should('not.exist');

            cy.wait('@deleteData').its('response.statusCode').should('equal', 204);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');

            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).then(($rowAfterDelete) => {
                const firstFlowNameAfterDelete = $rowAfterDelete.text().trim();
                cy.expect(firstFlowName).to.not.equal(firstFlowNameAfterDelete);
            });
        });
    });
});
