describe('Import/Export - Export:', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/import/export/index/export`);
        });
    });

    it('@settings: Create export with default profile', () => {
        cy.get('.sw-settings-import-export-view-export').should('be.visible');

        // TODO: Improve selectors
        cy.get('.sw-entity-single-select')
            .typeSingleSelectAndCheck('E2E', '.sw-entity-single-select');
        cy.get('.sw-button.sw-button--primary').click();
    });
});
