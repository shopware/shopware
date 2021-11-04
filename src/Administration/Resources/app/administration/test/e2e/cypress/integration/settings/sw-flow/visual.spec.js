// / <reference types="Cypress" />

describe('Flow builder: Visual testing', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        // Clean previous state and prepare Administration
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.setLocaleToEnGb();
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@visual: @check appearance of flow builder workflow', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/flow`,
            method: 'POST'
        }).as('getData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag`,
            method: 'POST'
        }).as('setTag');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST'
        }).as('getTag');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings'
        });
        cy.get('#sw-flow').click();
        cy.wait('@getData').its('response.statusCode').should('equal', 200);

        cy.sortAndCheckListingAscViaColumn('Name', 'Contact form sent');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // ToDo: Reintroduce snapshot in NEXT-18572
        //  cy.takeSnapshot('[Flow builder] Listing', '.sw-flow-list__grid');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__row', 'Order placed').scrollIntoView();
        cy.contains('.sw-data-grid__row a', 'Order placed').click();
        cy.get('.sw-loader').should('not.exist');
        cy.takeSnapshot('[Flow builder] Detail', '.sw-flow-detail');

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.takeSnapshot('[Flow builder] Flow tab', '.sw-flow-detail-flow');

        cy.get('.sw-flow-detail-flow__position-plus').click();

        cy.get('.sw-flow-sequence-selector__add-condition').scrollIntoView().click();
        cy.get('.sw-flow-sequence-condition__selection-rule')
            .typeSingleSelect('Cart >= 0', '.sw-flow-sequence-condition__selection-rule');

        cy.get('.sw-card-view__content').scrollTo('bottom');

        cy.takeSnapshot('[Flow builder] Detail, Condition IF with 2 selectors', '.sw-flow-detail');

        cy.get('.sw-flow-sequence__false-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence-action__selection-action');
        cy.handleModalSnapshot('Tag information');
        cy.takeSnapshot('[Flow builder] Tag modal', '.sw-flow-tag-modal');

        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('Special order');
        cy.get('.sw-select-result-list-popover-wrapper').contains('Add "Special order"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.wait('@setTag').its('response.statusCode').should('equal', 204);
        cy.get('.sw-select-result-list-popover-wrapper').contains('Special order');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');

        cy.wait('@getTag').its('response.statusCode').should('equal', 200);
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Set status', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-set-order-state-modal').should('be.visible');
        cy.handleModalSnapshot('Set status');
        cy.takeSnapshot('[Flow builder] Set order modal', '.sw-flow-set-order-state-modal');

        cy.get('#sw-field--config-order').select('Done').should('have.value', 'completed');
        cy.get('.sw-flow-set-order-state-modal__save-button').click();
        cy.get('.sw-flow-set-order-state-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__add-button').click();
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Generate document', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-generate-document-modal').should('be.visible');
        cy.handleModalSnapshot('Create document');
        cy.takeSnapshot('[Flow builder] Generate document modal', '.sw-flow-generate-document-modal');

        cy.get('.sw-flow-generate-document-modal__type-select')
            .typeSingleSelect('Invoice', '.sw-flow-generate-document-modal__type-select');
        cy.get('.sw-flow-generate-document-modal__save-button').click();
        cy.get('.sw-flow-generate-document-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__add-button').click();
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Send email', '.sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-mail-send-modal').should('be.visible');
        cy.handleModalSnapshot('Send mail');
        cy.takeSnapshot('[Flow builder] Send email modal', '.sw-flow-mail-send-modal');

        cy.get('.sw-flow-mail-send-modal__mail-template-select')
            .typeSingleSelect('Contact form', '.sw-flow-mail-send-modal__mail-template-select');

        cy.get('.sw-flow-mail-send-modal__save-button').click();
        cy.get('.sw-flow-mail-send-modal').should('not.exist');

        cy.takeSnapshot('[Flow builder] Simple flow builder with details', '.sw-flow-detail');
    });
});
