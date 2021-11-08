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
            cy.clock(now, ['Date']);
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index`);
        });
    });

    // eslint-disable-next-line no-undef
    after(() => {
        page = null;
    });

    it('@visual: check appearance of basic im/ex profile workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}//search/import-export-log`,
            method: 'POST'
        }).as('getData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/language`,
            method: 'POST'
        }).as('getLanguages');

        cy.get('.sw-import-export-view-import').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-import-export').click();
        cy.wait('@getData')
            .its('response.statusCode').should('equal', 200);

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
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/prepare`,
            method: 'POST'
        }).as('prepare');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/process`,
            method: 'POST'
        }).as('process');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'POST'
        }).as('importExportLog');

        cy.get('.sw-import-export-view-import').should('be.visible');
        cy.contains('[href="#/sw/import-export/index/export"]', 'Export').click();

        // Take snapshot for visual testing
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Import export] Detail, Export overview', '.sw-import-export-view-export');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-exporter__profile-select')
            .typeSingleSelectAndCheck(
                'Default product',
                '.sw-import-export-exporter__profile-select'
            );

        cy.get('.sw-import-export-progress__start-process-action').click();

        // Prepare request should be successful
        cy.wait('@prepare')
            .its('response.statusCode').should('equal', 200);

        // Process request should be successful
        cy.wait('@process')
            .its('response.statusCode').should('equal', 204);

        // Import export log request should be successful
        cy.wait('@importExportLog')
            .its('response.statusCode').should('equal', 200);

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--createdAt a', 'color : #fff');
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Take snapshot for visual testing
        cy.awaitAndCheckNotification('The export was started');
        cy.awaitAndCheckNotification('The export "Default product" was completed successfully.');
        cy.takeSnapshot('[Import export] Detail, Overview after export', '.sw-import-export-activity');

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            // check reworked log info modal
            cy.clickContextMenuItem(
                '.sw-import-export-activity__log-info-action',
                '.sw-context-button__button',
                `${page.elements.dataGridRow}--0`
            );

            cy.get('.sw-import-export-activity-log-info-modal').should('be.visible');
            cy.get('.sw-import-export-activity-log-info-modal__description-list').should('be.visible');
            // Take snapshot for visual testing
            cy.takeSnapshot('[Import export] reworked log info modal after export', '.sw-import-export-activity-log-info-modal');
        });
    });

    it('@visual: check appearance of basic import workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/prepare`,
            method: 'POST'
        }).as('prepare');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/process`,
            method: 'POST'
        }).as('process');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-log`,
            method: 'POST'
        }).as('importExportLog');

        // Take snapshot for visual testing
        cy.get('.sw-import-export-view-import').should('be.visible');
        cy.get('.sw-data-grid__skeleton').should('not.exist');
        cy.takeSnapshot('[Import export] Detail, Import overview', '.sw-import-export-view-import');

        // Upload a fixture CSV file with a single product
        cy.get('.sw-file-input__file-input')
            .attachFile('csv/single-product.csv');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-importer > .sw-field').click();
        cy.contains('Default product').click();

        // Start the import progress
        cy.get('.sw-import-export-progress__start-process-action').click();
        cy.get('.sw-import-export-progress__start-process-action').should('be.disabled');

        // Prepare request should be successful
        cy.wait('@prepare')
            .its('response.statusCode').should('equal', 200);

        // Process request should be successful
        cy.wait('@process')
            .its('response.statusCode').should('equal', 204);

        // Import export log request should be successful
        cy.wait('@importExportLog')
            .its('response.statusCode').should('equal', 200);

        // The activity logs should contain an entry for the succeeded import
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'Default product');
        cy.get('.sw-data-grid__skeleton').should('not.exist');

        // Change color of the element to ensure consistent snapshots
        cy.changeElementStyling('.sw-data-grid__cell--createdAt', 'color : #fff');

        // Take snapshot for visual testing
        cy.awaitAndCheckNotification('The import was started');
        cy.awaitAndCheckNotification('The import "Default product" was completed successfully');
        cy.takeSnapshot('[Import export] Detail, Overview after import', '.sw-import-export-activity');

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            // check added summary modal
            cy.clickContextMenuItem(
                '.sw-import-export-activity__results-action',
                '.sw-context-button__button',
                `${page.elements.dataGridRow}--0`
            );

            cy.get('.sw-import-export-activity-result-modal').should('be.visible');
            cy.get('.sw-import-export-activity-result-modal__info').should('be.visible');
            // Take snapshot for visual testing
            cy.takeSnapshot('[Import export] summary modal after import', '.sw-import-export-activity-result-modal');
        });
    });

    it('should check appearance of import/export wizard', () => {
        cy.onlyOnFeature('FEATURE_NEXT_15998');

        cy.visit(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);

        cy.get(page.elements.importExportCreateNewProfileButton)
            .should('be.visible')
            .and('not.be.disabled')
            .contains('Add new profile')
            .click();

        cy.get('.sw-modal__dialog')
            .should('be.visible');

        cy.get(page.elements.importExportProfileNameField)
            .clearTypeAndCheck('Custom profile for products');

        cy.get(page.elements.importExportObjectTypeSelect)
            .typeSingleSelectAndCheck('Product', page.elements.importExportObjectTypeSelect);

        // make sure that button is not disabled, to prevent a possible flaky test
        cy.get(`${page.elements.modalFooter} ${page.elements.primaryButton}`)
            .should('be.visible')
            .and('not.be.disabled');

        cy.takeSnapshot('[Import export] Wizard modal, general step', '.sw-modal__dialog');

        cy.get(`${page.elements.modalFooter} ${page.elements.primaryButton}`)
            .click();

        cy.get('.sw-file-input__dropzone').should('be.visible');

        cy.takeSnapshot('[Import export], Wizard modal, upload csv file step', '.sw-modal__dialog');

        cy.get(`${page.elements.modalFooter}`)
            .contains('Skip CSV upload')
            .should('be.visible')
            .click();

        cy.get(page.elements.importExportAddMappingButton)
            .should('be.visible')
            .click();

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 #mappedKey-0`).typeAndCheck('id');

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`)
            .typeSingleSelectAndCheck('id', `${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`);

        cy.get(page.elements.importExportAddMappingButton)
            .click();

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 #mappedKey-0`).typeAndCheck('taxId');

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`)
            .typeSingleSelectAndCheck('taxId', `${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`);

        cy.get(page.elements.importExportAddMappingButton)
            .click();

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 #mappedKey-0`).typeAndCheck('productNumber');

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`)
            .typeSingleSelectAndCheck('productNumber', `${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`);

       cy.get(page.elements.importExportAddMappingButton)
            .click();

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 #mappedKey-0`).typeAndCheck('stock');

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`)
            .click()

        cy.get('.sw-select-result-list__content')
            .should('be.visible')
            .contains(/^stock$/)
            .click();

        cy.get(page.elements.importExportAddMappingButton)
            .click();

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 #mappedKey-0`).typeAndCheck('name');

        cy.get(`${page.elements.importExportMappingGrid} ${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`)
            .typeSingleSelectAndCheck(
                'translations.DEFAULT.name',
                `${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}`
            );

        // make sure the result list of the select element is closed
        cy.get('.sw-select-result-list__content')
            .should('not.exist');

        cy.takeSnapshot('[Import export] Wizard modal, mappings step')
    });
});
