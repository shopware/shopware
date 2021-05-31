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

    // TODO: Test skipped because of flaky behaviour, fix and unskip with NEXT-15480
    it.skip('@settings @base: Create and read profile', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/import-export-profile`,
            method: 'post'
        }).as('saveData');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Expect modal to be displayed and add mapping button to be disabled first

        cy.get('.sw-import-export-edit-profile-modal-mapping').should('be.visible');
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.disabled');

        // Fill in name and object type
        cy.get('#sw-field--profile-label').type('Basic');
        cy.get('.sw-import-export-edit-profile-modal__object-type-select')
            .typeSingleSelectAndCheck(
                'Media',
                '.sw-import-export-edit-profile-modal__object-type-select'
            );

        // Fill in all required mappings
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.enabled');

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
        cy.get('#mappedKey-0').type('id');
        cy.get('.sw-import-export-entity-path-select__selection')
            .first().typeSingleSelectAndCheck(
                'id',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
        cy.get('#mappedKey-1').should('exist');
        cy.get('#mappedKey-0').type('createdAt');
        cy.get('.sw-import-export-entity-path-select__selection').first()
            .typeSingleSelectAndCheck(
                'createdAt',
                '.sw-data-grid__row--0 .sw-import-export-entity-path-select:nth-of-type(1)'
            );

        // Perform add new mapping
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();

        // Expect a new grid row to be visible
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Save the profile
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Verify that created profile is inside profile listing
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('Basic');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'Basic');
    });

    // TODO: Test skipped because of flaky behaviour, fix and unskip with NEXT-15480
    it.skip('@settings: Update and read profile', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'patch'
        }).as('saveData');

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
        cy.get('[name="sw-field--profile-label"]').clearTypeAndCheck('Updated E2E');
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Verify updated profile is in listing
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('Updated E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'Updated E2E');
    });

    // TODO: Test skipped because of flaky behaviour, fix and unskip with NEXT-15480
    it.skip('@settings: Delete profile', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/import-export-profile/*`,
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
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
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify deleted item is not present
        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').click();
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
        cy.get('.sw-import-export-view-profiles__listing .sw-data-grid__body').should('not.contain', 'E2E');
    });
});
