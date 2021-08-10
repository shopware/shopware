import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Check import dry run functionality', () => {
    let page = null;

    before(() => {
        cy.onlyOnFeature('FEATURE_NEXT_8097');
    });

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/import`);
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@base @settings: Perform import dry run with product profile', () => {
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

        cy.get('.sw-import-export-view-import').should('be.visible');

        // Upload a fixture CSV file with a single product
        cy.get('.sw-file-input__file-input')
            .attachFile({
                filePath: 'csv/single-product.csv',
                fileName: 'single-product.csv',
                mimeType: 'text/csv'
            });

        // File upload component should display file name
        cy.get('.sw-file-input__file-headline').should('contain', 'single-product.csv');

        // Start button should be disabled in the first place
        cy.get('.sw-import-export-progress__start-process-dryrun-action').should('be.disabled');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-importer__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-importer__profile-select');

        // Start the import progress
        cy.get('.sw-import-export-progress__start-process-dryrun-action').should('not.be.disabled');
        cy.get('.sw-import-export-progress__start-process-dryrun-action').click();
        cy.get('.sw-import-export-progress__start-process-dryrun-action').should('be.disabled');

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

        // The activity logs should contain an entry for the succeeded import
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName .sw-label`)
            .should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Succeeded');

        // Open the context menu of new activity log entry
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--actions .sw-context-button__button`)
            .should('be.visible')
            .click();

        // Check for show results context menu entry and open results table
        cy.get('.sw-context-menu .sw-import-export-activity__results-action')
            .should('be.visible')
            .click();

        // Check that results table is visible and has expected content
        cy.get('.sw-import-export-activity-result-modal .sw-grid').should('be.visible');
        cy.get('.sw-import-export-activity-result-modal__column-product-insert').should('contain', '1');
        cy.get('.sw-import-export-activity-result-modal__column-tax-insert').should('contain', '1');
    });
});
