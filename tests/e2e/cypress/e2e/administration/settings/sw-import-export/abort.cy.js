import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Check activities in progress can be aborted', () => {
    let page = null;

    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile', {
            id: '534dd6561cea480f95660f2960f441d4',
        }).then(() => {
            cy.authenticate().then((auth) => {
                cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${auth.access}`,
                        'Content-Type': 'application/json',
                    },
                    method: 'POST',
                    url: '/api/_action/import-export/prepare',
                    qs: {
                        response: true,
                    },
                    body: {
                        'profileId': '534dd6561cea480f95660f2960f441d4',
                        'expireDate': '2099-01-01',
                    },
                });
            });
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/export`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@base @settings: Abort export in progress', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'POST',
        }).as('importExportLog');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/cancel`,
            method: 'POST',
        }).as('importExportCancel');

        // Intially load the activity logs
        cy.wait('@importExportLog')
            .its('response.statusCode').should('equal', 200);

        // There should be one activity in progress
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Processing');

        // Abort export in progress
        cy.clickContextMenuItem(
            '.sw-import-export-activity__abort-process-action',
            '.sw-context-button__button',
            `${page.elements.dataGridRow}--0`,
        );

        // Wait for activity to be canceled
        cy.wait('@importExportCancel')
            .its('response.statusCode').should('equal', 204);

        // Wait for activities to be reloaded
        cy.wait('@importExportLog')
            .its('response.statusCode').should('equal', 200);

        // There should be one aborted activity
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Aborted');

        // Open the context menu of aborted activity entry
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions .sw-context-button__button`)
            .should('be.visible')
            .click();

        // Check that context menu entry to abort is no longer existing
        cy.get('.sw-context-menu .sw-import-export-activity__abort-process-action')
            .should('not.exist');
    });
});
