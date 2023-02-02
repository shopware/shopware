// / <reference types="Cypress" />

describe('Flow builder: set entity custom field testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.loginViaApi().then(() => {
                return cy.createProductFixture();
            }).then(() => {
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
                                label: { 'en-GB': 'my_custom_text_field' }
                            }
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
                                    { label: { 'en-GB': 'Option2' }, value: 'option2' }
                                ]
                            }
                        }
                    ],
                    relations: [
                        {
                            entityName: 'order'
                        },
                        {
                            entityName: 'customer'
                        }
                    ]
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

        // Fill all fields
        cy.get('#sw-field--flow-name').type('Order placed v1');
        cy.get('#sw-field--flow-priority').type('10');
        cy.get('.sw-flow-detail-general__general-active .sw-field--switch__input').click();

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.get('.sw-flow-trigger__input-field').type('order placed');
        cy.get('.sw-flow-trigger__search-result').should('be.visible');
        cy.get('.sw-flow-trigger__search-result').eq(0).click();

        cy.get('.sw-flow-sequence-selector').should('be.visible');
        cy.get('.sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_text_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_text_field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').should('be.visible');
        cy.contains('.sw-flow-set-entity-custom-field-modal__custom-field-value .sw-field__label', 'my_custom_text_field');
        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').type('my custom value');

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.contains('.sw-flow-sequence-action__action-name', 'Change custom field content');
        cy.get('.sw-flow-sequence-action__action-description').should('be.visible');
    });

    // NEXT-19144: Fix dependency on previous test case
    it('@settings: set entity custom field test field option', { tags: ['quarantined', 'pa-business-ops'] }, () => {
        cy.get('.sw-flow-sequence-action__actions').should('be.visible');
        cy.get('.sw-flow-sequence-action__add-button').click();

        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_text_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_text_field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value-options').should('be.visible');
        cy.get('#sw-field--fieldOptionSelected').find('option').then(options => {
            const actual = [...options].map(o => o.value);
            expect(actual).to.deep.equal(['upsert', 'create', 'clear']);
        });

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field .sw-entity-single-select__selection').type('my_custom_multiple_field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'my_custom_multiple_field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value-options').should('be.visible');
        cy.get('#sw-field--fieldOptionSelected').find('option').then(options => {
            const actual = [...options].map(o => o.value);
            expect(actual).to.deep.equal(['upsert', 'create', 'clear', 'add', 'remove']);
        });

        cy.get('#sw-field--fieldOptionSelected').select('clear').should('have.value', 'clear');

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-value').should('not.exist');

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.contains('.sw-flow-sequence-action__action-name', 'Change custom field content');
        cy.get('.sw-flow-sequence-action__action-description').should('be.visible');
    });

    // NEXT-19144: Fix dependency on previous test case
    it('@settings: test fields are invalid', { tags: ['quarantined', 'pa-business-ops'] }, () => {
        cy.get('.sw-flow-sequence-action__actions').should('be.visible');
        cy.get('.sw-flow-sequence-action__add-button').click();

        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Change custom field content', '.sw-flow-sequence-action__selection-action');
        cy.get('.sw-flow-set-entity-custom-field-modal').should('be.visible');

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.get('.has--error').should('be.visible');
        cy.contains('.sw-field__error', 'This field must not be empty.');

        cy.get('.sw-flow-set-entity-custom-field-modal__custom-field-set .sw-entity-single-select__selection').type('My custom field');
        cy.get('.sw-select-result').should('be.visible');
        cy.get('.sw-select-option--1').should('not.exist');
        cy.contains('.sw-select-option--0', 'My custom field').click();

        cy.get('.sw-flow-set-entity-custom-field-modal__save-button').click();

        cy.get('.has--error').should('be.visible');
        cy.contains('.sw-field__error', 'This field must not be empty.');
    });
});
