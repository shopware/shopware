describe('Index page on various viewports', () => {

    beforeEach(() => {
        cy.visit('/');
    });

    context('720p resolution', () => {
        beforeEach(() => {
            // run these tests as if in a desktop
            // browser with a 720p monitor
            cy.viewport(1280, 720)
        });

        it('displays full header', () => {
            cy.get('.nav.main-navigation-menu').should('be.visible');
            cy.get('.nav-main-toggle').should('not.be.visible');
        });
    });

    context('iphone-6 resolution', () => {
        beforeEach(() => {
            // run these tests as if in a mobile browser
            // and ensure our responsive UI is correct
            cy.viewport('iphone-6');
        });

        it('displays mobile menu on click', () => {
            cy.get('.nav.main-navigation-menu').should('not.be.visible');
            cy.get('.header-main .menu-button .nav-main-toggle-btn').should('be.visible').click();
            cy.get('.offcanvas.is-left.is-open').should('be.visible');
        });
    });
});
