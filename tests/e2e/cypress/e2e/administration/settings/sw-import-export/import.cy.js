import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Check import functionality', () => {
    let page = null;

    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile').then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/import`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@base @settings: Perform import with product profile', { tags: ['pa-system-settings', 'VUE3'] }, () => {
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

        cy.get('.sw-import-export-view-import').should('be.visible');

        // Upload a fixture CSV file with a single product
        cy.get('.sw-file-input__file-input')
            .attachFile('csv/single-product.csv');

        // File upload component should display file name
        cy.get('.sw-file-input__file-headline').should('contain', 'single-product.csv');

        // Start button should be disabled in the first place
        cy.get('.sw-import-export-progress__start-process-action').should('be.disabled');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-importer__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-importer__profile-select');

        // Start the import progress
        cy.get('.sw-import-export-progress__start-process-action').should('not.be.disabled');
        cy.get('.sw-import-export-progress__start-process-action').click();
        cy.get('.sw-import-export-progress__start-process-action').should('be.disabled');

        // Prepare request should be successful
        cy.wait('@prepare').its('response.statusCode').should('equal', 200);

        // Process request should be successful
        cy.wait('@process').its('response.statusCode').should('equal', 204);

        // Import export log request should be successful
        cy.wait('@importExportLog').its('response.statusCode').should('equal', 200);

        // The activity logs should contain an entry for the succeeded import
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'Succeeded');

        // Verify that the imported product exists in product listing
        cy.visit(`${Cypress.env('admin')}#/sw/product/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Example product');
    });
});
