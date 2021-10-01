import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Profiles: Test crud operations', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
        });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });


    it('@settings @base: Create and read profile', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();

        cy.get('.sw-modal__dialog').should('be.visible');

        cy.get('#sw-field--profile-label').typeAndCheck('Basic');

        cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
            cy.get('.sw-import-export-edit-profile-general__text').should('be.visible');
        });

        cy.skipOnFeature('FEATURE_NEXT_15998', () => {
            // Expect modal to be open with content
            cy.get('.sw-import-export-edit-profile-modal__text').should('be.visible');
        });

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            // switch to mapping tab
            cy.contains('.sw-import-export-edit-profile-modal .sw-tabs-item', 'Mappings').click();
        });

        // Expect modal to be displayed and add mapping button to be disabled first
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.disabled');

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            // switch back to general tab
            cy.contains('.sw-import-export-edit-profile-modal .sw-tabs-item', 'General').click();
            // expect to see the general tab content

            cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
                cy.get('.sw-import-export-edit-profile-general__text').should('be.visible');
            });

            cy.skipOnFeature('FEATURE_NEXT_15998', () => {
                cy.get('.sw-import-export-edit-profile-modal__text').should('be.visible');
            });
        });

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
                cy.get('.sw-import-export-edit-profile-general__object-type-select')
                    .typeSingleSelectAndCheck(
                        'Media',
                        '.sw-import-export-edit-profile-general__object-type-select'
                    );
            });

            cy.skipOnFeature('FEATURE_NEXT_15998', () => {
                cy.get('.sw-import-export-edit-profile-modal__object-type-select')
                    .typeSingleSelectAndCheck(
                        'Media',
                        '.sw-import-export-edit-profile-modal__object-type-select'
                    );
            });
        });


        cy.skipOnFeature('FEATURE_NEXT_15998', () => {
            cy.get('.sw-import-export-edit-profile-modal__object-type-select')
                .typeSingleSelectAndCheck(
                    'Media',
                    '.sw-import-export-edit-profile-modal__object-type-select'
                );
        });

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
                cy.get('.sw-import-export-edit-profile-general__type-select')
                    .typeSingleSelectAndCheck(
                        'Import and export',
                        '.sw-import-export-edit-profile-general__type-select'
                    );
                // switch to mapping tab
                cy.contains('.sw-import-export-edit-profile-modal .sw-tabs-item', 'Mappings').click();
            });
        });

        cy.skipOnFeature('FEATURE_NEXT_15998', () => {
            cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
                cy.get('.sw-import-export-edit-profile-modal__type-select')
                    .typeSingleSelectAndCheck(
                        'Import and export',
                        '.sw-import-export-edit-profile-modal__type-select'
                    );
                // switch to mapping tab
                cy.contains('.sw-import-export-edit-profile-modal .sw-tabs-item', 'Mappings').click();
            });
        });

        // Fill in all required mappings (add mapping button should be enabled now)
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.enabled');

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
        cy.get('#mappedKey-0').type('id');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
                'id',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
            // check that the required id field is system required and the requirement can't be unchecked
            cy.get('.sw-data-grid__row--0 .sw-import-export-edit-profile-modal-mapping__required-by-user-switch:not([style*="display: none"]) input')
                .should('be.disabled');
        });

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
        cy.get('#mappedKey-1').should('exist');
        cy.get('#mappedKey-0').type('createdAt');
        cy.get('.sw-import-export-entity-path-select__selection').first()
            .typeSingleSelectAndCheck(
                'createdAt',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        cy.onlyOnFeature('FEATURE_NEXT_8097', () => {
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
        });

        // Perform add new mapping
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();

        // Expect a new grid row to be visible
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Save the profile
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'Basic');
    });

    it('@settings @base: Duplicate profile', () => {
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

    it('@settings: Update and read profile', () => {
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

    it('@settings @base: Update and read profile in different content language', () => {
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

    it('@settings @base: Create profile disabled in different content language', () => {
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

    it('@settings: Delete profile', () => {
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

    it('@settings @base: Create an export profile', () => {
        cy.onlyOnFeature('FEATURE_NEXT_8097');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'POST'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();

        cy.get('.sw-modal__dialog').should('be.visible');

        cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
            cy.get('.sw-import-export-edit-profile-general__text').should('be.visible');
        });

        cy.skipOnFeature('FEATURE_NEXT_15998', () => {
            // Expect modal to be open with content
            cy.get('.sw-import-export-edit-profile-modal__text').should('be.visible');
        });

        // Fill in name and object type
        cy.get('#sw-field--profile-label').typeAndCheck('Basic');

        cy.onlyOnFeature('FEATURE_NEXT_15998', () => {
            cy.get('.sw-import-export-edit-profile-general__type-select')
                .typeSingleSelectAndCheck(
                    'Export',
                    '.sw-import-export-edit-profile-general__type-select'
                );
            cy.get('.sw-import-export-edit-profile-general__object-type-select')
                .typeSingleSelectAndCheck(
                    'Media',
                    '.sw-import-export-edit-profile-general__object-type-select'
                );
        });

        cy.skipOnFeature('FEATURE_NEXT_15998', () => {
            cy.get('.sw-import-export-edit-profile-modal__type-select')
                .typeSingleSelectAndCheck(
                    'Export',
                    '.sw-import-export-edit-profile-modal__type-select'
                );
            cy.get('.sw-import-export-edit-profile-modal__object-type-select')
                .typeSingleSelectAndCheck(
                    'Media',
                    '.sw-import-export-edit-profile-modal__object-type-select'
                );
        });

        // switch to mapping tab
        cy.contains('.sw-import-export-edit-profile-modal .sw-tabs-item', 'Mappings').click();

        // add mapping button should be enabled now
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.enabled');

        // Only add name mapping
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
        cy.get('#mappedKey-0').type('name');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
                'fileName',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        // Save the profile
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');
        cy.get(`${page.elements.dataGridRow}`).should('contain', 'Basic');

        // Verify the export profile cant be used for importing
        cy.visit(`${Cypress.env('admin')}#/sw/import-export/index/import`);
        cy.get('.sw-import-export-importer__profile-select').click();
        cy.get('.sw-import-export-importer__profile-select input').type('Basic');
        cy.get('.sw-select-result-list__empty').should('be.visible');
    });
});
