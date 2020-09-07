import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Export:', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/export`);
            });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@base @settings: Create export with product profile', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/import-export/prepare`,
            method: 'post'
        }).as('prepare');

        cy.route({
            url: `${Cypress.env('apiPath')}/_action/import-export/process`,
            method: 'post'
        }).as('process');

        cy.route({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'post'
        }).as('importExportLog');

        cy.get('.sw-import-export-view-export').should('be.visible');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-exporter__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-exporter__profile-select');
        cy.get('.sw-import-export-progress__start-process-action').click();

        // Prepare request should be successful
        cy.wait('@prepare').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Process request should be successful
        cy.wait('@process').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Import export log request should be successful
        cy.wait('@importExportLog').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        // Progress bar and log should be visible
        cy.get('.sw-import-export-progress__progress-bar-bar').should('be.visible');
        cy.get('.sw-import-export-progress__stats').should('be.visible');

        // The download button should be there
        cy.get('.sw-import-export-progress__download-action').should('be.visible');

        // The activity logs should contain an entry for the succeeded export
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Succeeded');

        cy.awaitAndCheckNotification('The export was completed successfully.');
    });
});
