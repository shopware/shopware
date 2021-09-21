// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Flow builder: Test acl privilege', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
        });
    });

    it('@settings: can view flow builder', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
        });

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // click on first element in grid
        cy.get(`${page.elements.dataGridRow}--0`)
            .contains('Order placed')
            .click();
    });

    it('@settings: can edit flow builder', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer'
            },
            {
                key: 'flow',
                role: 'editor'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
        });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'PATCH'
        }).as('updateData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed');

        cy.clickContextMenuItem(
            '.sw-flow-list__item-edit',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
            'Edit',
            true
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
        cy.wait('@updateData').its('response.statusCode').should('equal', 204);

        // Verify updated element
        cy.get(page.elements.smartBarBack).click();
        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed v2');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed v2');
    });

    it('@settings: can create flow builder', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer'
            },
            {
                key: 'flow',
                role: 'editor'
            },
            {
                key: 'flow',
                role: 'creator'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
        });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow`,
            method: 'POST'
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
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify successful save
        cy.get('.sw-loader__element').should('not.exist');
        cy.get('.smart-bar__header h2').contains('Order placed v1');

        // Verify created element
        cy.get(page.elements.smartBarBack).click();
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed v1');
    });

    it('@settings: can delete flow', () => {
        const page = new SettingsPageObject();

        cy.loginAsUserWithPermissions([
            {
                key: 'flow',
                role: 'viewer'
            },
            {
                key: 'flow',
                role: 'deleter'
            }
        ]).then(() => {
            cy.visit(`${Cypress.env('admin')}#/sw/flow/index`);
        });

        cy.intercept({
            url: `${Cypress.env('apiPath')}/flow/*`,
            method: 'DELETE'
        }).as('deleteData');

        cy.get('.sw-flow-list').should('be.visible');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible')
            .contains('Order placed');

        cy.clickContextMenuItem(
            `${page.elements.contextMenu}-item--danger`,
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
            'Delete',
            true
        );

        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();
        cy.get(page.elements.modal).should('not.exist');

        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`)
            .contains('Order placed').should('not.exist');
    });
});
