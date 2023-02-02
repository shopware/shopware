describe('Tax provider sorting: Visual testing', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        // Clean previous state and prepare Administration
        cy.setLocaleToEnGb()
            .then(() => {
                return cy.createDefaultFixture('tax-provider', {
                    name: 'Tax provider one',
                    identifier: 'tax-provider-one',
                    active: true,
                    priority: 1,
                }, 'tax-provider');
            }).then(() => {
                return cy.createDefaultFixture('tax-provider', {
                    name: 'Tax provider two',
                    identifier: 'tax-provider-two',
                    active: true,
                    priority: 2,
                }, 'tax-provider');
            })
            .then(() => {
                cy.openInitialPage(Cypress.env('admin'));
            });
    });

    it('@base @settings: should sort tax provider accordingly', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tax-provider`,
            method: 'POST',
        }).as('getTaxProviders');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/tax-provider/**`,
            method: 'PATCH',
        }).as('patchTaxProviders');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });

        cy.get('#sw-settings-tax').click();

        cy.wait('@getTaxProviders').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-tax-provider-list-button__change-priority')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-tax-provider-sorting-modal').should('be.visible');
        cy.get('.sw-sortable-list__item').should('have.length', 2);

        const firstItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(1)';
        const lastItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(2)';

        cy.get(firstItem).contains('Tax provider one');

        // this moves the first item in front of the last item
        cy.get(firstItem).dragTo(lastItem);

        cy.get(firstItem).contains('Tax provider two');
        cy.get(lastItem).contains('Tax provider one');

        cy.get('.sw-settings-tax-provider-sorting-modal__save-button').click();

        cy.wait('@patchTaxProviders').its('response.statusCode').should('equal', 204);
        cy.wait('@patchTaxProviders').its('response.statusCode').should('equal', 204);

        cy.get('.sw-settings-tax-provider-sorting-modal').should('not.exist');

        cy.get('.sw-settings-tax-provider-list-button__change-priority')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-tax-provider-sorting-modal').should('be.visible');

        cy.get(firstItem).contains('Tax provider two');
        cy.get(lastItem).contains('Tax provider one');
    });

    it('@base: settings: should default to original order on cancel', () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/tax-provider`,
            method: 'POST',
        }).as('getTaxProviders');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/tax-provider/**`,
            method: 'PATCH',
        }).as('patchTaxProviders');

        cy.get('.sw-dashboard-index__welcome-text').should('be.visible');
        cy.clickMainMenuItem({
            targetPath: '#/sw/settings/index',
            mainMenuId: 'sw-settings',
        });

        cy.get('#sw-settings-tax').click();

        cy.wait('@getTaxProviders').its('response.statusCode').should('equal', 200);

        cy.get('.sw-settings-tax-provider-list-button__change-priority')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-tax-provider-sorting-modal').should('be.visible');
        cy.get('.sw-sortable-list__item').should('have.length', 2);

        const firstItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(1)';
        const lastItem = '.sw-sortable-list > .sw-sortable-list__item:nth-child(2)';

        cy.get(firstItem).contains('Tax provider one');

        // this moves the first item in front of the last item
        cy.get(firstItem).dragTo(lastItem);

        cy.get(firstItem).contains('Tax provider two');
        cy.get(lastItem).contains('Tax provider one');

        cy.get('.sw-settings-tax-provider-sorting-modal__cancel-button').click();

        cy.get('.sw-settings-tax-provider-sorting-modal').should('not.exist');

        cy.get('.sw-settings-tax-provider-list-button__change-priority')
            .should('be.visible')
            .click();

        cy.get('.sw-settings-tax-provider-sorting-modal').should('be.visible');

        cy.get(firstItem).contains('Tax provider one');
        cy.get(lastItem).contains('Tax provider two');
    });
});
