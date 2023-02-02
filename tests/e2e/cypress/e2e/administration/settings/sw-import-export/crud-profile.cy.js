import SettingsPageObject from '../../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Profiles: Test crud operations', () => {
    let page = null;

    beforeEach(() => {
        cy.loginViaApi().then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@settings: Create and read update only profile', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('#sw-field--profile-label').typeAndCheck('Basic');
        cy.get('.sw-import-export-edit-profile-general__object-type-select')
            .typeSingleSelectAndCheck(
                'Product',
                '.sw-import-export-edit-profile-general__object-type-select'
            );
        cy.get('.sw-import-export-edit-profile-general__type-select')
            .typeSingleSelectAndCheck(
                'Import and export',
                '.sw-import-export-edit-profile-general__type-select'
            );

        // Set profile to update only
        cy.get('.sw-import-export-edit-profile-import-settings__create-switch input').click();

        // Go to mapping page and add description mapping
        cy.get('.sw-import-export-new-profile-wizard__footer-right-button-group button').click();

        cy.contains('.sw-import-export-new-profile-wizard__footer-right-button-group button', 'Skip CSV upload')
            .click();

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action')
            .should('be.enabled')
            .click();

        cy.get('#mappedKey-0').typeAndCheck('description');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
            'description',
            '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
        );

        // Save the profile
        cy.get('.sw-import-export-new-profile-wizard__footer-right-button-group button').click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal .sw-import-export-edit-profile-modal').should('not.exist');
        cy.get(page.elements.dataGridRow).should('contain', 'Basic');
    });

    it('@settings @base: Create and read profile with wizard', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Expect modal to be open with content
        cy.get('.sw-import-export-edit-profile-general__text').should('be.visible');

        // Fill in name and object type
        cy.get('#sw-field--profile-label').type('BasicWizard');
        cy.get(page.elements.importExportObjectTypeSelect)
            .typeSingleSelectAndCheck(
                'Media',
                page.elements.importExportObjectTypeSelect
            );

        cy.get(page.elements.importExportTypeSelect)
            .typeSingleSelectAndCheck(
                'Import and export',
                page.elements.importExportTypeSelect
            );

        // navigate to next wizard page (CSV upload)
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();
        cy.get(`${page.elements.importExportProfileWizard}-csv-page__text`).should('be.visible');

        // skip csv upload in this test
        cy.contains(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button`, 'Skip').click();
        cy.get(`${page.elements.importExportProfileWizard}-mapping-page__text`).should('be.visible');

        // Check if required mapping have been filled out directly
        cy.get('#mappedKey-0')
            .should('be.visible')
            .and('have.value', 'id')

        cy.contains('.sw-data-grid__row--0 .sw-import-export-entity-path-select__selection-text', 'id')
            .should('be.visible');

        // Fill in all required mappings (add mapping button should be enabled now)
        cy.get(page.elements.importExportAddMappingButton).should('be.enabled');

        cy.get(page.elements.importExportAddMappingButton).click();
        cy.get('#mappedKey-0').type('id');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
                'id',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        // check that the required id field is system required and the requirement can't be unchecked
        cy.get('.sw-data-grid__row--0 .sw-import-export-edit-profile-modal-mapping__required-by-user-switch:not([style*="display: none"]) input')
            .should('be.disabled');

        cy.get(page.elements.importExportAddMappingButton).click();
        cy.get('#mappedKey-1').should('exist');
        cy.get('#mappedKey-0').type('createdAt');
        cy.get('.sw-import-export-entity-path-select__selection').first()
            .typeSingleSelectAndCheck(
                'createdAt',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        // check that the createdAt field is can be required by the user
        cy.get('.sw-data-grid__row--0 .sw-import-export-edit-profile-modal-mapping__required-by-user-switch:not([style*="display: none"]) input')
            .should('be.enabled')
            .click();

        // add a default value for this mapping
        cy.get('.sw-data-grid__row--0 input[name="useDefaultValue-0"]').click();
        cy.get('.sw-data-grid__row--0 input[name="defaultValue-0"]')
            .should('be.enabled')
            .type('default')
            .should('have.value', 'default');

        // Perform add new mapping
        cy.get(page.elements.importExportAddMappingButton).click();

        // Expect a new grid row to be visible
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Save / add the profile
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();

        // Save request should be successful
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get(page.elements.importExportProfileWizard).should('not.exist');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'BasicWizard');
    });

    it('@settings @base: Createprofile with wizard and import mapping via CSV file', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/import-export/mapping-from-template`,
            method: 'POST'
        }).as('mappingData');

        // Perform create new profile action
        cy.get(page.elements.importExportProfileListing).should('be.visible');
        cy.get(page.elements.importExportCreateNewProfileButton).click();

        // Expect modal to be open with content
        cy.get(`${page.elements.importExportWizardGeneralPage}__text`).should('be.visible');

        // Fill in name and object type
        cy.get('#sw-field--profile-label').type('UploadWizard');
        cy.get(page.elements.importExportObjectTypeSelect)
            .typeSingleSelectAndCheck(
                'Media',
                page.elements.importExportObjectTypeSelect
            );

        cy.get(page.elements.importExportTypeSelect)
            .typeSingleSelectAndCheck(
                'Import and export',
                page.elements.importExportTypeSelect
            );

        // navigate to next wizard page (CSV upload)
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();
        cy.get(`${page.elements.importExportWizardCsvPage}__text`).should('be.visible');

        // csv upload
        cy.get('.sw-file-input__file-input').attachFile({
            filePath: 'csv/profile-mapping.csv',
            fileName: 'profile-mapping.csv',
            mimeType: 'text/csv'
        });
        cy.wait('@mappingData').its('response.statusCode').should('equal', 200);

        cy.contains(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button`, 'Next').click();
        cy.get(`${page.elements.importExportWizardMappingPage}__text`).should('be.visible');

        // sort mappings
        cy.get(`${page.elements.dataGridRow}--0 .icon--regular-chevron-down-xxs`)
            .click();

        cy.get(`${page.elements.dataGridRow}--3 .icon--regular-chevron-up-xxs`)
            .click();

        // Check imported mapping
        cy.get(page.elements.importExportAddMappingButton).should('be.enabled');

        // first csv column 'id'
        cy.get('#mappedKey-0').should('have.value', 'some_custom_field');
        cy.get(
            `${page.elements.dataGridRow}--0 ${page.elements.importExportEntityPathSelect}:nth-of-type(1)`
        ).should('contain', 'Not mapped');

        // second csv column 'some_custom_field'
        cy.get('#mappedKey-1').should('have.value', 'id');
        cy.get(
            `${page.elements.dataGridRow}--1 ${page.elements.importExportEntityPathSelect}:nth-of-type(1)`
        ).should('contain', 'id');

        // third csv column 'title'
        cy.get('#mappedKey-2').should('have.value', 'user_email');
        cy.get(
            `${page.elements.dataGridRow}--2 ${page.elements.importExportEntityPathSelect}:nth-of-type(1)`
        ).should('contain', 'user.email');

        // fourth csv column 'user_email'
        cy.get('#mappedKey-3').should('have.value', 'title');
        cy.get(
            `${page.elements.dataGridRow}--3 ${page.elements.importExportEntityPathSelect}:nth-of-type(1)`
        ).should('contain', 'translations.DEFAULT.title');

        // Save / add the profile
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(page.elements.importExportProfileWizard).should('not.exist');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'UploadWizard');
    });

    it('@settings @base: Duplicate profile', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Perform open action
        cy.clickContextMenuItem(
            '.sw-import-export-view-profiles__listing-duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Wait for detail modal
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        // Save / close the modal
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();
        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Verify duplicated profile is in listing
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'Copy of: E2E');
    });

    it('@settings: Update and read profile', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'PATCH'
        }).as('saveData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').should('be.visible');
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clear();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').type('E2E', {
            delay: 400
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
            `${page.elements.dataGridRow}--0`
        );

        // Wait for detail modal
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        // Update the profile with a new name
        cy.get('[name="sw-field--profile-label"]').clearTypeAndCheck('Updated E2E');
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-modal.sw-import-export-edit-profile-modal').should('not.exist');

        // Verify updated profile is in listing
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'Updated E2E');
    });

    it('@settings @base: Update and read profile in different content language', { tags: ['pa-system-settings'] }, () => {
        // sw-simple-search component got refactored on NEXT-16271 to address loosing input issue
        cy.onlyOnFeature('FEATURE_NEXT_16271');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'PATCH'
        }).as('saveData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-profile`,
            method: 'POST'
        }).as('loadData');

        // change content language to german
        cy.get('.sw-language-switch__select').typeSingleSelectAndCheck('Deutsch', '.sw-language-switch__select');
        cy.wait('@loadData')
            .its('response.statusCode').should('equal', 200);

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Perform open action
        cy.clickContextMenuItem(
            '.sw-import-export-view-profiles__listing-open-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Wait for detail modal
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        // Update the profile with a new name
        cy.get('[name="sw-field--profile-label"]').clearTypeAndCheck('German only');
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Update the search
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('German only');

        // Verify updated profile is in listing
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'German only');

        // switch back to english
        cy.get('.sw-language-switch__select').typeSingleSelectAndCheck('English', '.sw-language-switch__select');
        cy.wait('@loadData')
            .its('response.statusCode').should('equal', 200);

        // Update the search
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Verify profile name is still the same in english
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'E2E');
    });

    it('@settings @base: Create profile disabled in different content language', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-profile`,
            method: 'POST'
        }).as('loadData');

        // check that the add new profile button is enabled in the system language
        cy.get('.sw-import-export-view-profiles__create-action').should('be.enabled');

        // change content language to german
        cy.get('.sw-language-switch__select').typeSingleSelectAndCheck('Deutsch', '.sw-language-switch__select');
        cy.wait('@loadData')
            .its('response.statusCode').should('equal', 200);

        // check that the add new profile button is disabled in other languages
        cy.get('.sw-import-export-view-profiles__create-action').should('be.disabled');
    });

    it('@settings: Delete profile', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'delete'
        }).as('deleteData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/import-export-profile`,
            method: 'POST'
        }).as('search');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');

        // wait for the search request before checking the search result
        cy.wait('@search').its('response.statusCode').should('equals', 200);

        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');

        // Perform delete action
        cy.clickContextMenuItem(
            '.sw-import-export-view-profiles__listing-delete-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Wait for delete confirmation modal
        cy.get('.sw-modal__dialog').should('be.visible');
        cy.get('.sw-modal__dialog .sw-listing__confirm-delete-text')
            .should('contain', 'Are you sure you want to delete this item?');

        // Confirm deletion
        cy.get('.sw-modal__dialog .sw-button--danger').click();

        // Delete request should be successful
        cy.wait('@deleteData').its('response.statusCode').should('equal', 204);

        // Verify deleted item is not present
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        // Wait for the data grid to be fully loaded before entering something in the search bar, otherwise
        // it can happen that the first characters of the search text are cut off, e.g. "2E" instead of "E2E"
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');

        // wait for the search request before checking the search result
        cy.wait('@search').its('response.statusCode').should('equals', 200);
        cy.get('.sw-import-export-view-profiles__listing .sw-data-grid__body').should('not.contain', 'E2E');
    });

    it('@settings @base: Create an export profile with wizard', { tags: ['pa-system-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Expect modal to be open with content
        cy.get('.sw-import-export-edit-profile-general__text').should('be.visible');

        // Fill in name and object type
        cy.get('#sw-field--profile-label').type('BasicExportWizard');
        cy.get(page.elements.importExportObjectTypeSelect)
            .typeSingleSelectAndCheck(
                'Media',
                page.elements.importExportObjectTypeSelect
            );

        cy.get(page.elements.importExportTypeSelect)
            .typeSingleSelectAndCheck(
                'Export',
                page.elements.importExportTypeSelect
            );

        // navigate to next wizard page (CSV upload)
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();
        cy.get(`${page.elements.importExportProfileWizard}-csv-page__text`).should('be.visible');

        // skip csv upload in this test
        cy.contains(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button`, 'Skip').click();
        cy.get(`${page.elements.importExportProfileWizard}-mapping-page__text`).should('be.visible');

        // add mapping button should be enabled now
        cy.get(page.elements.importExportAddMappingButton).should('be.enabled');

        // Only add name mapping
        cy.get(page.elements.importExportAddMappingButton).click();
        cy.get('#mappedKey-0').type('name');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
                'fileName',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        // Save / add the profile
        cy.get(`${page.elements.importExportProfileWizard}__footer-right-button-group .sw-button--primary`).click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get(`${page.elements.importExportProfileWizard}`).should('not.exist');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'BasicExportWizard');

        // Verify the export profile cant be used for importing
        cy.visit(`${Cypress.env('admin')}#/sw/import-export/index/import`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-import-export-importer__profile-select').click();
        cy.get('.sw-import-export-importer__profile-select input').type('BasicExportWizard');
        cy.get('.sw-select-result-list__empty').should('be.visible');
    });
});
