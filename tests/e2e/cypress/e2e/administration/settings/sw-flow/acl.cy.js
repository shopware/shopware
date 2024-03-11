// / <reference types="Cypress" />

import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Test acl privilege', () => {
    beforeEach(() => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
    });

    it('@settings: can view flow builder', { tags: ['pa-services-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.contains(`${page.elements.dataGridRow}`, 'Order placed').click();
    });

    it('@settings: can edit flow builder', {tags: ['pa-services-settings', 'quarantined']}, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'editor',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.clickContextMenuItem(
            '.sw-flow-list__item-edit',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
            'Edit',
            true,
        );

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('#sw-field--flow-name').clearTypeAndCheck('Order placed v2');
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

            cy.intercept({
                url: `${Cypress.env('apiPath')}/flow/*`,
                method: 'PATCH',
            }).as('updateData');
            cy.get('.sw-flow-detail__save').click();
            cy.wait('@updateData').its('response.statusCode').should('equal', 204);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');

            // Verify updated element
            cy.get(page.elements.smartBarBack).click({force: true});
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');

            cy.get('.sw-flow-leave-page-modal').should('be.visible');
            cy.get('.sw-flow-leave-page-modal__leave-page').click();
            cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed v2');
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
            cy.contains(`${page.elements.dataGridRow}`, 'Order placed v2').should('be.visible');
        });
    });

    it('@settings: can create flow builder', { tags: ['pa-services-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'editor',
            },
            {
                key: 'flow',
                role: 'creator',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

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
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        // Save
        cy.get('.sw-flow-detail__save').click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify successful save
        cy.get('.sw-loader__element').should('not.exist');
        cy.contains('.smart-bar__header h2', 'Order placed v1');

        // Verify created element
        cy.get(page.elements.smartBarBack).click({force: true});
        cy.contains(`${page.elements.dataGridRow}`, 'Order placed v1').should('be.visible');
    });

    it('@settings: can delete flow', { tags: ['pa-services-settings'] }, () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer',
            },
            {
                key: 'flow',
                role: 'deleter',
            },
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'DELETE',
        }).as('deleteData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
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

            cy.contains(`${page.elements.dataGridRow}`, firstFlowName).should('not.exist');

            cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).then(($rowAfterDelete) => {
                const firstFlowNameAfterDelete = $rowAfterDelete.text().trim();
                cy.expect(firstFlowName).to.not.equal(firstFlowNameAfterDelete);
            });
        });
    });
});
