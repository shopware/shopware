/// <reference types="Cypress" />

const selectors = {
    nameInput: 'input[name=sw-field--productFeatureSet-name]',
    saveButton: '.smart-bar__actions > button.sw-button--primary',
    emptyFieldsCard: '.sw-settings-product-feature-set-card__empty-state',
    addFieldButton: `.sw-settings-product-feature-set-card__empty-state > button.sw-button--ghost`,
    fieldsModal: '.sw-settings-product-feature-sets-modal__options',
    fieldsModalFooter: '.sw-modal__footer',
    radioGroup: '.sw-field__radio-group',
    button: 'button.sw-button',
    valueTable: 'table.sw-data-grid__table tbody',
    valueTableRow: '.sw-data-grid__row',
    checkbox: 'input[type=checkbox]'
}

describe('Essential characteristics: Test create operation', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi()
                    .then(() => {
                        cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/product/feature/sets/create`);
                    });
            });
    })

    it('@settings: create a feature set', () => {
        // Request we want to wait for later
        cy.server();
        cy.route({
            url: '/api/v*/product-feature-set',
            method: 'post'
        }).as('saveData');
        cy.route({
            url: '/api/v*/product-feature-set/*',
            method: 'patch'
        }).as('addField');

        // Create country
        cy.get(selectors.nameInput).typeAndCheck('Lorem ipsum');

        cy.get(selectors.saveButton).click();

        // Verify creation
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(selectors.addFieldButton).click();

        cy.get(selectors.fieldsModal).within(() => {
            cy.get(selectors.radioGroup)
                .contains('Product information')
                .click();
        })

        cy.get(selectors.fieldsModalFooter).within(() => {
            cy.get(selectors.button)
                .contains('Next')
                .click();
        })

        cy.get(selectors.valueTable).within(() => {
            cy.get(selectors.valueTableRow).first().within(() => {
                cy.get(selectors.checkbox).click();
            });
        })

        cy.get(selectors.fieldsModalFooter).within(() => {
            cy.get(selectors.button)
                .contains('Add')
                .click();
        })

        // Verify creation
        cy.wait('@addField').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });
    });
});
