import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Import/Export - Export:', () => {
    let page = null;

    beforeEach(() => {
        cy.setToInitialState().then(() => {
            cy.loginViaApi();
        }).then(() => {
            return cy.createDefaultFixture('import-export-profile');
        }).then(() => {
            return cy.createProductFixture();
        })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/import-export/index/export`);
            });

        page = new SettingsPageObject();
    });

    afterEach(() => {
        page = null;
    });

    it('@settings: Create export with product profile', () => {
        cy.get('.sw-import-export-view-export').should('be.visible');

        // Select fixture profile for product entity
        cy.get('.sw-import-export-exporter__profile-select')
            .typeSingleSelectAndCheck('E2E', '.sw-import-export-exporter__profile-select');
        cy.get('.sw-import-export-progress__start-process-action').click();

        // Progress bar and log should be visible
        cy.get('.sw-import-export-progress__progress-bar-bar').should('be.visible');
        cy.get('.sw-import-export-progress__stats').should('be.visible');

        // The download button should be there
        cy.get('.sw-import-export-progress__download-action').should('be.visible');

        // The activity logs should contain an entry for the succeeded export
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0`).should('be.visible');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--profileName`)
            .should('contain', 'E2E');
        cy.get(`.sw-import-export-activity ${page.elements.dataGridRow}--0 .sw-data-grid__cell--state`)
            .should('contain', 'succeeded');
    });
});
