// / <reference types="Cypress" />

describe('Flow builder: set entity custom field testing', () => {
    beforeEach(() => {
        cy.createProductFixture().then(() => {
            return cy.createCustomerFixture();
        })
            .then(() => {
                return cy.createDefaultFixture('custom-field-set', {
                    customFields: [
                        {
                            active: true,
                            name: 'my_custom_text_field',
                            type: 'text',
                            config: {
                                componentName: 'sw-field',
                                customFieldPosition: 1,
                                customFieldType: 'text',
                                type: 'text',
                                helpText: { 'en-GB': 'help text' },
                                label: { 'en-GB': 'my_custom_text_field' },
                            },
                        },
                        {
                            active: true,
                            name: 'my_custom_multiple_field',
                            type: 'select',
                            config: {
                                componentName: 'sw-multi-select',
                                customFieldPosition: 2,
                                customFieldType: 'select',
                                label: { 'en-GB': 'my_custom_multiple_field' },
                                options: [
                                    { label: { 'en-GB': 'Option1' }, value: 'option1' },
                                    { label: { 'en-GB': 'Option2' }, value: 'option2' },
                                ],
                            },
                        },
                    ],
                    relations: [
                        {
                            entityName: 'order',
                        },
                        {
                            entityName: 'customer',
                        },
                    ],
                });
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/flow/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@settings: set entity custom field flow', { tags: ['pa-business-ops'] }, () => {
        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Check if no skeleton or loader exists
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        // Switch tab to flow
        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-detail-flow').should('exist');
        cy.get('.sw-flow-trigger__input-field').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        // Select trigger
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        // Add flow action
        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');

        // Show assign custom field modal and fill field set
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        // Select custom field
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_text_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_text_field').click();

        // Insert custom field value
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').should('be.visible');
        cy.contains('.sw-flow-set-entity-custom-field-modal__custom-field-value .sw-field__label', 'my_custom_text_field');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').type('my custom value');

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.contains('.sw-flow-sequence-action__action-name', 'Change custom field content');
        cy.get('.sw-flow-sequence-action__action-description').should('be.visible');
    });

    it('@settings: set entity custom field test field option', { tags: ['pa-business-ops'] }, () => {
        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Check if no skeleton or loader exists
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        // Switch tab to flow
        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-detail-flow').should('exist');
        cy.get('.sw-flow-trigger__input-field').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        // Select trigger
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        // Add flow action
        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__actions').should('be.visible');
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');

        // Show assign custom field modal and fill field set
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        // Select custom field
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_text_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_text_field').click();

        // Switch to other custom field
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value-options').should('be.visible');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_multiple_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_multiple_field').click();

        // Switch mode to clear
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value-options').should('be.visible');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value-options').type('Clear');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'Clear').click();
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').should('not.exist');

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.contains('.sw-flow-sequence-action__action-name', 'Change custom field content');
        cy.get('.sw-flow-sequence-action__action-description').should('be.visible');
    });

    it('@settings: test fields are invalid', { tags: ['pa-business-ops'] }, () => {
        cy.get('.sw-flow-list').should('be.visible');
        cy.get('.sw-flow-list__create').click();

        // Verify "create" page
        cy.contains('.smart-bar__header h2', 'New flow');

        // Check if no skeleton or loader exists
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        // Switch tab to flow
        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-detail-flow').should('exist');
        cy.get('.sw-flow-trigger__input-field').should('be.visible');
        cy.get('.sw-loader').should('not.exist');

        // Select trigger
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        // Add flow action
        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__actions').should('be.visible');
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');

        // Show assign custom field modal and try to save it without any input
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');
        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();
        cy.get('.has--error').should('be.visible');
        cy.contains('.sw-field__error', 'This field must not be empty.');

        // Select custom field set
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        // Try to save without selection custom field
        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();
        cy.get('.has--error').should('be.visible');
        cy.contains('.sw-field__error', 'This field must not be empty.');
    });
});
