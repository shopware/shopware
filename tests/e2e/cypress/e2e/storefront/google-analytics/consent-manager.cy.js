describe('Google Analytics: New analytics cookie in Cookie Consent Manager', () => {
    it('@cookies: There is a new analytics cookie in the manager if it is activated for the saleschannel', { tags: ['ct-storefront'] }, () => {
        cy.setAnalyticsFixtureToSalesChannel(true);

        cy.visit('/');

        // Open cookie configurator
        cy.get('.js-cookie-configuration-button > .btn').click();

        cy.get('.offcanvas-cookie').should('be.visible');
        cy.get('.offcanvas-cookie-group').eq(1).contains('Statistic');

        cy.get('.offcanvas-cookie-group').eq(1).find('.offcanvas-cookie-entry').contains('Google Analytics');
    });

    it('@cookies: There is no statistics group in the cookie manager if google analytics is not activated for the saleschannel', { tags: ['ct-storefront'] }, () => {
        cy.setAnalyticsFixtureToSalesChannel(false);

        cy.visit('/');

        // Open cookie configurator
        cy.get('.js-cookie-configuration-button > .btn').click();

        cy.get('.offcanvas-cookie').should('be.visible');
        cy.get('.offcanvas-cookie-group').should('not.contain', 'Statistic');
    });

    it('@cookies: Google Analytics cookies will be set and removed again', { tags: ['ct-storefront'] }, () => {
        cy.setAnalyticsFixtureToSalesChannel(true);

        cy.intercept({
            method: 'GET',
            url: '/cookie/offcanvas',
        }).as('cookieOffcanvas');

        cy.visit('/');

        cy.getCookie('_swag_ga_ga').should('be.null');

        // Open cookie configurator
        cy.get('.js-cookie-configuration-button > .btn').click();

        cy.get('.offcanvas').should('exist');
        cy.get('.offcanvas').should('be.visible');

        cy.wait('@cookieOffcanvas')
            .its('response.statusCode').should('equal', 200);

        cy.get('.offcanvas-cookie').should('be.visible');
        cy.get('.offcanvas-cookie-group').eq(1).find('.custom-control-label').first().click();
        cy.get('.js-offcanvas-cookie-submit').click();

        cy.get('.offcanvas').should('not.exist');

        // Reload the page to verify later if the cookie configuration is set.
        cy.reload();

        cy.window().then((win) => {
            cy.waitUntil(() => cy.getCookie('_swag_ga_ga').then(cookie => cookie && cookie.value !== null));

            cy.get('.offcanvas').should('not.exist');

            // Open cookie configurator
            cy.wrap(win.PluginManager.getPluginInstances('CookieConfiguration')[0]).invoke('openOffCanvas');

            cy.wait('@cookieOffcanvas')
                .its('response.statusCode').should('equal', 200);

            cy.get('.offcanvas').should('exist');
            cy.get('.offcanvas-cookie').should('be.visible');
            cy.get('.offcanvas-cookie-group').eq(1).find('.custom-control-label').first().click();
            cy.get('.js-offcanvas-cookie-submit').click();

            cy.waitUntil(() => cy.getCookie('_swag_ga_ga').then(cookie => !cookie || cookie.value === null));
        });
    });
});
