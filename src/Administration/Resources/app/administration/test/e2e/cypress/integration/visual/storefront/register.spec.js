import AccountPageObject from '../../../support/pages/account.page-object';

describe('Account - Register: Visual tests', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                return cy.createCustomerFixture()
            })
    });

    it('@visual: check appearance of basic registration workflow', () => {
        const page = new AccountPageObject();
        cy.visit('/account/login');
        cy.get(page.elements.registerCard).should('be.visible');

        // Take snapshot for visual testing
        cy.takeSnapshot('Registration', page.elements.registerCard, { widths: [375, 1920] });
    });
});
