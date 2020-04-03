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

    it('@settings: Create and read profile', () => {
        cy.server();
        cy.route({
            url: '/api/v1/import-export-profile',
            method: 'post'
        }).as('saveData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Perform create new profile action
        cy.get('.sw-import-export-view-profiles__create-action').click();

        // Expect modal to be displayed and add mapping button to be disabled first
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');
        cy.get('.sw-import-export-edit-profile-modal-mapping').should('be.visible');
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.disabled');

        // Fill in name and object type
        cy.get('[name="sw-field--profile-name"]').type('Basic');
        cy.get('.sw-import-export-edit-profile-modal__object-type-select')
            .typeSingleSelectAndCheck('Product', '.sw-import-export-edit-profile-modal__object-type-select');

        // Perform add new mapping
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();

        // Expect a new grid row to be visible
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // Fill in mapping
        cy.get(`${page.elements.dataGridRow}--0 [name="mappedKey-0"]`).type('id');
        cy.get('.sw-import-export-entity-path-select')
            .typeSingleSelectAndCheck('id', '.sw-import-export-entity-path-select');

        // Save the profile
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Verify that created profile is inside profile listing
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').type('Basic');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'Basic');
    });

    it('@settings: Update and read profile', () => {
        cy.server();
        cy.route({
            url: '/api/v1/import-export-profile/*',
            method: 'patch'
        }).as('saveData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').typeAndCheck('E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');

        // Perform open action
        cy.clickContextMenuItem(
            '.sw-import-export-view-profiles__listing-open-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Wait for detail modal
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        // Update the profile with a new name
        cy.get('[name="sw-field--profile-name"]').clearTypeAndCheck('Updated E2E');
        cy.get('.sw-import-export-edit-profile-modal__save-action').click();

        // Save request should be successful
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-import-export-edit-profile-modal').should('not.be.visible');

        // Verify updated profile is in listing
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('Updated E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'Updated E2E');
    });

    it('@settings: Delete profile', () => {
        cy.server();
        cy.route({
            url: '/api/v1/import-export-profile/*',
            method: 'delete'
        }).as('deleteData');

        cy.get('.sw-import-export-view-profiles__listing').should('be.visible');

        // Search for given profile
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').typeAndCheck('E2E');
        cy.get(`${page.elements.dataGridRow}--0`).should('contain', 'E2E');

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
        cy.get('.sw-modal__dialog .sw-button--primary').click();

        // Delete request should be successful
        cy.wait('@deleteData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Verify deleted item is not present
        cy.get('.sw-import-export-view-profiles__search input[type="text"]').clearTypeAndCheck('E2E');
        cy.get('.sw-import-export-view-profiles__listing .sw-data-grid__body').should('not.contain', 'E2E');
    });
});
