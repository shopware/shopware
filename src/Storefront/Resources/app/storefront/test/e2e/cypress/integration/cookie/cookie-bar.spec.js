describe('Test if the cookie bar works correctly', () => {

    it('Should not show the acceptAll cookies in the cookie bar when config value is not set', () => {
        // go to storefront homepage
        cy.visit('/');

        // wait for product listing
        cy.get('.cms-element-product-listing').should('be.visible');

        // wait for cookie banner
        cy.get('.cookie-permission-container').should('be.visible');

        // check if the acceptAll button is not visible
        cy.get('.js-cookie-accept-all-button').should('not.exist');
    });

    it('Should show the accept all button in cookie bar and accept all cookies when the user clicks the button', () => {
        // activate the acceptAllCookies button
        cy.authenticate().then((result) => {
            const requestConfig = {
                headers: {
                    Authorization: `Bearer ${result.access}`
                },
                method: 'POST',
                url: `api/_action/system-config/batch`,
                body: {
                    null: {
                        'core.basicInformation.acceptAllCookies': true
                    }
                }
            };

            return cy.request(requestConfig);
        });

        // go to storefront homepage
        cy.visit('/');

        // should wait of cookieOffcanvas
        cy.intercept({
            method: 'GET',
            url: '/cookie/offcanvas',
        }).as('cookieOffcanvas');

        // wait for product listing
        cy.get('.cms-element-product-listing').should('be.visible');

        // wait for cookie banner
        cy.get('.cookie-permission-container').should('be.visible');

        // click on the acceptAll button
        cy.get('.js-cookie-accept-all-button').should('be.visible')
            .click();

        // wait until the offcanvas is open
        cy.wait('@cookieOffcanvas');

        // wait until the offcanvas is closed
        cy.get('.offcanvas-cookie').should('not.exist');

        // reload
        cy.reload(true);

        // check if cookie bar is non existent
        cy.get('.cms-element-product-listing').should('be.visible');
        cy.get('.cookie-permission-container').should('not.be.visible');
    });
});
