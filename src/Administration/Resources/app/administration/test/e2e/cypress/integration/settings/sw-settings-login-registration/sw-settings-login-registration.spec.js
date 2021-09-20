describe('Login / Registration: Test show operations on templates', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/settings/login/registration/index`);
            });
    });

    it('@settings: Customer scope', () => {
        // Request we want to wait for later
        cy.intercept({
            url: `**/${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST'
        }).as('saveSettings');

        cy.get('input[name="core.systemWideLoginRegistration.isCustomerBoundToSalesChannel"]')
            .scrollIntoView()
            .click()
            .should('have.value', 'on');
        cy.get('.smart-bar__content .sw-button--primary').click();

        cy.wait('@saveSettings').its('response.statusCode').should('equal', 204);

        cy.get('.sw-sales-channel-switch').scrollIntoView();
        cy.get('#salesChannelSelect')
            .typeSingleSelectAndCheck(
                'Storefront',
                '#salesChannelSelect'
            );

        cy.get('input[name="core.systemWideLoginRegistration.isCustomerBoundToSalesChannel"]').scrollIntoView();
        cy.get('input[name="core.systemWideLoginRegistration.isCustomerBoundToSalesChannel"]').should('have.value', 'on');
    });
});
