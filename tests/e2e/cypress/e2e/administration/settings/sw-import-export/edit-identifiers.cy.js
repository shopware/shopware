import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Profiles: Test editing identifiers and import', () => {
    let page = null;

    beforeEach(() => {
        cy.createDefaultFixture('import-export-profile').then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@settings: Edit identfiers', { tags: ['pa-services-settings', 'quarantined'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'PATCH',
        }).as('saveData');

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

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').should('be.visible');
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clear();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').type('E2E', {
            delay: 400,
        });

        cy.get('.sw-import-export-view-profiles__search input[type="text"]')
            .invoke('val', 'E2E')
            .should('have.value', 'E2E');

        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Perform open action
        cy.clickContextMenuItem(
            '.sw-import-export-view-profiles__listing-open-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`,
        );

        // Wait for detail modal
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        // Go to identifier mapping tab
        cy.get('.sw-import-export-edit-profile-modal .sw-tabs-item:nth-of-type(3)').click();

        // Wait for identifier mapping tab grid
        cy.get('.sw-import-export-edit-profile-modal-identifiers__grid').should('be.visible');

        // Select different identifiers for product and manufacturer
        cy.get(`.sw-import-export-edit-profile-modal-identifiers__grid ${page.elements.dataGridRow}--0 .sw-single-select`)
            .typeSingleSelectAndCheck(
                'productNumber',
                `.sw-import-export-edit-profile-modal-identifiers__grid ${page.elements.dataGridRow}--0 .sw-single-select`,
            );
        cy.get(`.sw-import-export-edit-profile-modal-identifiers__grid ${page.elements.dataGridRow}--3 .sw-single-select`)
            .typeSingleSelectAndCheck(
                'translations.DEFAULT.name',
                `.sw-import-export-edit-profile-modal-identifiers__grid ${page.elements.dataGridRow}--3 .sw-single-select`,
            );

        // Save the profile
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal.sw-import-export-edit-profile-modal').should('not.exist');

        // Go to import tab
        cy.get('.sw-tabs-item:nth-of-type(1)').click();

        cy.get('.sw-import-export-view-import').should('be.visible');

        // Upload a fixture CSV file with a product and manufacturer updated by product number
        cy.get('.sw-file-input__file-input')
            .attachFile('csv/products-updated-by-identifiers.csv');

        // File upload component should display file name
        cy.get('.sw-file-input__file-headline').should('contain', 'products-updated-by-identifiers.csv');

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
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`, 'Product updated by product number');

        // Verify that the manufacturer was created only once identified by the name
        cy.visit(`${Cypress.env('admin')}#/sw/manufacturer/index`);
        cy.contains('.smart-bar__header', 'Manufacturers');
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Manufacturer', 'Manufacturer identified by name');
        cy.get(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`).should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--0 .sw-data-grid__cell--name`,
            'Manufacturer identified by name');
        cy.get(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`).should('be.visible');
        cy.contains(`${page.elements.dataGridRow}--1 .sw-data-grid__cell--name`, 'shopware AG');
        cy.get(`${page.elements.dataGridRow}--2`).should('not.exist');
    });
});
