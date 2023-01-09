describe('Payment: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        cy.setLocaleToEnGb()
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @settings: should sort payment methods accordingly', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
            method: 'POST',
        }).as('getPaymentMethods');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/payment-method/**`,
            method: 'PATCH',
        }).as('patchPaymentMethods');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });

        cy.get('#sw-settings-payment').click();

        cy.wait('@getPaymentMethods').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-payment-overview__sorting_modal_card').should('be.visible');
        cy.get('.sw-settings-payment-overview__sorting_modal_card')
            .find('.sw-button')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-payment-sorting-modal').should('be.visible');
        cy.get('.sw-sortable-list__item').should('have.length', 4);

        const firstItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(1)';
        const lastItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(4)';

        cy.get(firstItem).contains('Cash on delivery');

        // this moves the first item in front of the last item
        cy.get(firstItem).dragTo(lastItem);

        cy.get(firstItem).contains('Paid in advance');
        cy.get(lastItem).contains('Cash on delivery');

        cy.get('.sw-settings-payment-sorting-modal__save-button').click();

        cy.wait('@patchPaymentMethods').its('response.statusCode').should('equal', 204);
        cy.wait('@patchPaymentMethods').its('response.statusCode').should('equal', 204);

        cy.get('.sw-settings-payment-sorting-modal').should('not.exist');

        cy.get('.sw-settings-payment-overview__sorting_modal_card')
            .find('.sw-button')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-payment-sorting-modal').should('be.visible');

        cy.get(firstItem).contains('Paid in advance');
        cy.get(lastItem).contains('Cash on delivery');
    });

    it('@base: settings: should default to original order on cancel', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/payment-method`,
            method: 'POST',
        }).as('getPaymentMethods');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });

        cy.get('#sw-settings-payment').click();

        cy.wait('@getPaymentMethods').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-payment-overview__sorting_modal_card').should('be.visible');
        cy.get('.sw-settings-payment-overview__sorting_modal_card')
            .find('.sw-button')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-payment-sorting-modal').should('be.visible');
        cy.get('.sw-sortable-list__item').should('have.length', 4);

        const firstItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(1)';
        const lastItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(4)';

        cy.get(firstItem).contains('Cash on delivery');

        // this moves the first item in front of the last item
        cy.get(firstItem).dragTo(lastItem);

        cy.get(firstItem).contains('Paid in advance');
        cy.get(lastItem).contains('Cash on delivery');

        cy.get('.sw-settings-payment-sorting-modal__cancel-button').click();
        cy.get('.sw-settings-payment-sorting-modal').should('not.exist');

        cy.get('.sw-settings-payment-overview__sorting_modal_card').should('be.visible');
        cy.get('.sw-settings-payment-overview__sorting_modal_card')
            .find('.sw-button')
            .should('be.visible')
            .click();

        cy.get(firstItem).contains('Cash on delivery');
    });
});
