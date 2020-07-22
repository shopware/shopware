// / <reference types="Cypress" />

describe('Currency: Test crud operations', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set');
            });
    });

    it('@settings: create and read custom field', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/custom-field-set`,
            method: 'post'
        }).as('saveData');
        cy.route({
            url: `${Cypress.env('apiPath')}/custom-field-set/**/custom-fields`,
            method: 'post'
        }).as('saveData');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/create`);

        cy.get('#sw-field--set-name').clearTypeAndCheck('my_custom_field');

        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck('My custom field set');

        cy.get('.sw-select').click();
        cy.contains('.sw-select-result', 'Products').click({ force: true });
        cy.get('h2').click();
        cy.get('.sw-select__results-list').should('not.exist');
        cy.get('.sw-label').contains('Products');

        cy.get('.sw-empty-state').should('exist');

        // saving custom field
        cy.get('.sw-settings-set-detail__save-action').click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // adding custom fields
        cy.get('.sw-custom-field-list__add-button').click();

        cy.get('#sw-field--currentCustomField-name').clearTypeAndCheck('my_custom_field_first');
        cy.get('#sw-field--currentCustomField-config-customFieldType').select('Text field');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(1) input')
            .clearTypeAndCheck('This is a label');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(2) input')
            .clearTypeAndCheck('This is a placeholder');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(3) input')
            .clearTypeAndCheck('This is a help text');

        cy.get('.sw-modal__footer > .sw-button--primary').click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-custom-field-translated-labels input').should('have.value', 'My custom field set');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(3)').contains('This is a label');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(4)').contains('Text field');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(5)').contains('1');
    });

    it('@settings: edit custom field', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/custom-field-set/**/custom-fields/*`,
            method: 'patch'
        }).as('saveData');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        cy.get('.sw-grid-row.sw-grid__row--0 a').click();

        cy.get('.sw-custom-field-translated-labels input').clearTypeAndCheck('Another custom field set');

        cy.get('.sw-settings-set-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-context-button__button').click();
        cy.get('.sw-context-menu-item:nth-of-type(1)').click();

        cy.get('#sw-field--currentCustomField-config-customFieldPosition')
            .clearTypeAndCheck('2');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(1) input')
            .clearTypeAndCheck('Another label');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(2) input')
            .clearTypeAndCheck('Another placeholder');

        cy.get('.sw-custom-field-type-base .sw-field:nth-of-type(3) input')
            .clearTypeAndCheck('Another help text');

        cy.get('.sw-modal__footer > .sw-button--primary > .sw-button__content').click();
        cy.get('.sw-modal').should('not.exist');

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(3)').contains('Another label');

        cy.get('.sw-settings-set-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-custom-field-translated-labels input').should('have.value', 'Another custom field set');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(3)').contains('Another label');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(4)').contains('Text field');

        cy.get('.sw-grid__row--0 .sw-grid-column:nth-of-type(5)').contains('2');
    });

    it('@settings: delete custom field', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'post'
        }).as('saveData');

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        cy.get('.sw-grid-row.sw-grid__row--0 a').click();

        // delete custom field
        cy.get('.sw-context-button__button').click();
        cy.get('.sw-context-menu-item:nth-of-type(2)').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-custom-field-delete__description').contains('Are you sure that you want to delete this custom field?');
        cy.get('.sw-button--danger').click();
        cy.get('.sw-modal').should('not.exist');

        cy.get('.sw-empty-state').should('be.visible');
        cy.get('.sw-settings-set-detail__save-action').click();
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-empty-state').should('exist');
        cy.get('.sw-empty-state__title').contains('No custom fields yet.');

        cy.visit(`${Cypress.env('admin')}#/sw/settings/custom/field/index`);

        // delete custom field set
        cy.get('.sw-context-button__button').click();
        cy.get('.sw-context-menu-item--danger').click();

        cy.get('.sw-modal').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Do you really want to delete the set "My custom field" ?');
        cy.get('.sw-button--danger').click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 200);
            cy.get('.sw-modal').should('not.exist');
        });

        cy.get('.sw-empty-state').should('exist');
        cy.get('.sw-empty-state__title').contains('No custom fields yet.');
    });
});
