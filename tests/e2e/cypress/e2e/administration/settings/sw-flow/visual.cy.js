// / <reference types="Cypress" />

describe('Flow builder: Visual testing', () => {
    beforeEach(() => {
        cy.setLocaleToEnGb().then(() => {
            cy.openInitialPage(Cypress.env('admin'));
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@visual: @check appearance of flow builder workflow', { tags: ['pa-services-settings'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/flow`,
            method: 'POST',
        }).as('getData');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/tag`,
            method: 'POST',
        }).as('setTag');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tag`,
            method: 'POST',
        }).as('getTag');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });
        cy.get('#sw-flow').click();
        cy.wait('@getData').its('response.statusCode').should('equal', 200);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.sortAndCheckListingAscViaColumn('Name', 'Contact form sent');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        // ToDo: Reintroduce snapshot in NEXT-18572
        //  cy.takeSnapshot('[Flow builder] Listing', '.sw-flow-list__grid');

        cy.get('input.sw-search-bar__input').typeAndCheckSearchField('Order placed');
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.contains('.sw-data-grid__row', 'Order placed').scrollIntoView();
        cy.contains('.sw-data-grid__row a', 'Order placed').click();
        cy.get('.sw-loader').should('not.exist');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Detail', '.sw-flow-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-detail__tab-flow').click();
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Flow tab', '.sw-flow-detail-flow', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-detail-flow__position-plus').click();

        cy.get('.sw-flow-sequence-selector__add-condition').scrollIntoView().click();
        cy.get('.sw-flow-sequence-condition__selection-rule')
            .typeSingleSelect('Cart >= 0', '.sw-flow-sequence-condition__selection-rule');

        cy.get('.sw-card-view__content').scrollTo('bottom');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Detail, Condition IF with 2 selectors', '.sw-flow-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-sequence__false-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence__false-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Add tag', '.sw-flow-sequence__false-block .sw-flow-sequence-action__selection-action');
        cy.handleModalSnapshot('Add tag');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Tag modal', '.sw-flow-tag-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-tag-modal').should('be.visible');

        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('Special order');
        cy.contains('.sw-select-result-list-popover-wrapper', 'Add "Special order"');
        cy.get('.sw-flow-tag-modal__tags-field input')
            .type('{enter}');
        cy.wait('@setTag').its('response.statusCode').should('equal', 204);
        cy.contains('.sw-select-result-list-popover-wrapper', 'Special order');
        cy.get('.sw-flow-tag-modal__tags-field input').type('{esc}');

        cy.wait('@getTag').its('response.statusCode').should('equal', 200);
        cy.get('.sw-flow-tag-modal__save-button').click();
        cy.get('.sw-flow-tag-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-selector__add-action').click();
        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Assign status', '.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-set-order-state-modal').should('be.visible');
        cy.handleModalSnapshot('Assign status');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Set order modal', '.sw-flow-set-order-state-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('#sw-field--config-order').select('Done').should('have.value', 'completed');
        cy.get('.sw-flow-set-order-state-modal__save-button').click();
        cy.get('.sw-flow-set-order-state-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Generate documents', '.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-generate-document-modal').should('be.visible');
        cy.handleModalSnapshot('Generate documents');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Generate document modal', '.sw-flow-generate-document-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-generate-document-modal__type-multi-select').typeMultiSelectAndCheck('Invoice');

        cy.get('.sw-flow-generate-document-modal__save-button').click();
        cy.get('.sw-flow-generate-document-modal').should('not.exist');

        cy.get('.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action')
            .typeSingleSelect('Send email', '.sw-flow-sequence__true-block .sw-flow-sequence-action__selection-action');

        cy.get('.sw-flow-mail-send-modal').should('be.visible');
        cy.handleModalSnapshot('Send email');
        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Send email modal', '.sw-flow-mail-send-modal', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});

        cy.get('.sw-flow-mail-send-modal__mail-template-select')
            .typeSingleSelect('Contact form', '.sw-flow-mail-send-modal__mail-template-select');

        cy.get('.sw-flow-mail-send-modal__save-button').click();
        cy.get('.sw-flow-mail-send-modal').should('not.exist');

        cy.prepareAdminForScreenshot();
        cy.takeSnapshot('[Flow builder] Simple flow builder with details', '.sw-flow-detail', null, {percyCSS: '.sw-notification-center__context-button--new-available:after { display: none; }'});
    });
});
