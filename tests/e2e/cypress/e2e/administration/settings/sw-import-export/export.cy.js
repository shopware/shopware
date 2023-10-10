import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Export:', () => {
    let page = null;

    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile').then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/export`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@base @settings: Create export with product profile', { tags: ['pa-system-settings', 'VUE3'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/prepare`,
            method: 'POST',
        }).as('prepare');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/process`,
            method: 'POST',
        }).as('process');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'POST',
        }).as('importExportLog');

        cy.get('.sw-import-export-view-export').should('be.visible');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-exporter__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-exporter__profile-select');
        cy.get('.sw-import-export-progress__start-process-action').click();

        // Prepare request should be successful
        cy.wait('@prepare').its('response.statusCode').should('equal', 200);

        // Process request should be successful
        cy.wait('@process').its('response.statusCode').should('equal', 204);

        // Import export log request should be successful
        cy.wait('@importExportLog').its('response.statusCode').should('equal', 200);

        // The activity logs should contain an entry for the succeeded export
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Succeeded');
    });
});
