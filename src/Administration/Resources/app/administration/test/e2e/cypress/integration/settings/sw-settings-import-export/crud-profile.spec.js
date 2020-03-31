describe('Import/Export - Profiles: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/profiles`);
        });
    });

    it('@settings: Create and read profile', () => {
        cy.get('.sw-import-export-view-profiles__toolbar').should('be.visible');

        cy.get('.sw-import-export-view-profiles__create-action').click();
        cy.get('.sw-import-export-edit-profile-modal').should('be.visible');

        cy.get('.sw-import-export-edit-profile-modal-mapping').should('be.visible');
        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').should('be.disabled');

        cy.get('[name="sw-field--profile-name"]').type('Basic');
        cy.get('.sw-import-export-edit-profile-modal__object-type-select')
            .typeSingleSelectAndCheck('Product', '.sw-import-export-edit-profile-modal__object-type-select');

        cy.get('.sw-import-export-edit-profile-modal-mapping__add-action').click();
    });
});
