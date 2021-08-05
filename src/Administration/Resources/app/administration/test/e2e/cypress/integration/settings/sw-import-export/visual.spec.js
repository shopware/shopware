import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export:  Visual tests', () => {
    let page = null;

    // eslint-disable-next-line no-undef
    before(() => {
        cy.setToInitialState().then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            return cy.createProductFixture();
        });

        page = new SettingsPageObject();
    });

    beforeEach(() => {
        cy.loginViaApi().then(() => {
            // freezes the system time to Jan 1, 2018
            const now = new Date(2018, 1, 1);
            cy.clock(now);
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index`);
        });
    });

    // eslint-disable-next-line no-undef
    after(() => {
        page = null;
    });

    it('@visual: check appearance of basic im/ex profile workflow', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}//search/import-export-log`,
            method: 'post'
        }).as('getData');
        cy.route({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'post'
        }).as('getLanguages');

        cy.get('.sw-import-export-view-import').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-import-export').click();
        cy.wait('@getData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
        });

        cy.get('[href="#/sw/import-export/index/profiles"]').should('be.visible');
        cy.get('[href="#/sw/import-export/index/profiles"]').click();
        cy.get('.sw-page__main-content').should('be.visible');

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', 'Default category');
        cy.takeSnapshot('[Import export] Profiles overview',
            '.sw-import-export-view-profiles__listing');
    });

    it('@visual: check appearance of basic export workflow', () => {
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
        cy.contains('[href="#/sw/import-export/index/export"]', 'Export').click();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Import export] Detail, Export overview', '.sw-import-export-view-export');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-exporter__profile-select').click();
        cy.contains('Default product').click();
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
            cy.get('.sw-import-export-progress__stats-list-success').contains('Export successful');
        });

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--createdAt a', 'color : #fff');
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Take snapshot for visual testing
        cy.takeSnapshot('[Import export] Detail, Overview after export', '.sw-import-export-activity');
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
        cy.get('.sw-import-export-view-import').should('be.visible');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Import export] Detail, Import overview', '.sw-import-export-view-import');

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
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--createdAt', 'color : #fff');

        // Take snapshot for visual testing
        cy.contains('Import successful').should('be.visible');
        cy.takeSnapshot('[Import export] Detail, Overview after import', '.sw-import-export-activity');
    });
});
