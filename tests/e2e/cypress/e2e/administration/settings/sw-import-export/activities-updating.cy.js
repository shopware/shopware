import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Check activities in progress are updating', () => {
    let page = null;
    let logId = null;

    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile', {
            'id': '534dd6561cea480f95660f2960f441d4',
        }).then(() => {
            return cy.createProductFixture();
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
                }).then((response) => {
                    logId = response.body.log.id;
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
        logId = null;
    });

    it('@base @settings: Wait for in progress export to be updated', { tags: ['pa-services-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'POST',
        }).as('importExportLog');

        // There should be one log in progress
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--records`)
            .should('contain', '0');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Processing');

        // Start the actual export in background
        cy.getBearerAuth().then((auth) => {
            cy.request({
                headers: {
                    Accept: 'application/vnd.api+json',
                    Authorization: `Bearer ${auth.access}`,
                    'Content-Type': 'application/json',
                },
                method: 'POST',
                url: '/api/_action/import-export/process',
                body: {
                    'logId': logId,
                },
            });
        });

        // Import export log request should occur after a few seconds and be successful
        cy.wait('@importExportLog')
            .its('response.statusCode').should('equal', 200);

        // Log should have been updated to show succeded state
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--records`)
            .should('contain', '1');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Succeeded');

        // Notification should be shown announcing export being completed
        cy.awaitAndCheckNotification('Export "E2E" completed.');
    });
});
