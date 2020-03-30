describe('Import/Export - Import:', () => {
    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/import/export/index/import`);
        });
    });

    it('@settings: Create export with default profile', () => {
        cy.get('.sw-settings-import-export-view-import').should('be.visible');
    });
});
