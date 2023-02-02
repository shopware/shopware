import AccountPageObject from '../../../support/pages/account.page-object';

describe('Account - Login: Visual tests', () => {
    it('@visual: check appearance of basic storefront login workflow', { tags: ['pa-system-settings'] }, () => {
        if (!Cypress.env('testDataUsage')) {
            return;
        }

        const page = new AccountPageObject();
        cy.visit('/account/login');

        const user = Cypress.env('testDataUsage') ? 'kathie.jaeger@test.com' : 'test@example.com';
        cy.get('#loginMail').type(user);
        cy.get('#loginPassword').type('shopware');
        cy.get(`${page.elements.loginSubmit} [type="submit"]`).click();

        const accountHeader = Cypress.env('locale') === 'en-GB' ? 'Overview' : 'Übersicht';
        cy.get('.account-welcome h1').should((element) => {
            expect(element).to.contain(accountHeader);
        });

        // Take snapshot for visual testing
        cy.takeSnapshot(`${Cypress.env('testDataUsage') ? '[Update]' : '[Install]'} Account overview after login`,
            '.account-overview',
            {widths: [375, 1920]}
        );
    });
});
