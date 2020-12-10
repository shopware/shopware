import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Import:  Visual tests', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now);
        }).then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/import`);
            });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@visual: check appearance of basic import workflow', () => {
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

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('Import export -  Import overview', '.sw-import-export-view-import');

        // Upload a fixture CSV file with a single product
        cy.fixture('csv/single-product.csv').then(fileContent => {
            cy.get('.sw-file-input__file-input').upload(
                {
                    fileContent,
                    fileName: 'single-product.csv',
                    mimeType: 'text/csv'
                }, {
                    subjectType: 'input'
                }
            );
        });

        // Select fixture profile for product entity
        cy.get('.sw-import-export-importer > .sw-field').click();
        cy.contains('Default product').click();

        // Start the import progress
        cy.get('.sw-import-export-progress__start-process-action').click();
        cy.get('.sw-import-export-progress__start-process-action').should('be.disabled');

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

        // The activity logs should contain an entry for the succeeded import
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'Default product');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.changeElementStyling('.sw-data-grid__cell--createdAt', 'color : #fff');
        cy.takeSnapshot('Import export -  Overview after import', '.sw-import-export-activity');
    });
});
