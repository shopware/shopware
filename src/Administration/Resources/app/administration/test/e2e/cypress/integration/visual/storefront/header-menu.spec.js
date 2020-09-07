describe('Header menu:Visual tests', () => {

    beforeEach(() => {
        cy.visit('/');
    });

    context('720p resolution', () => {
        beforeEach(() => {
            // run these tests as if in a desktop
            // browser with a 720p monitor
            cy.viewport(1280, 720)
        });

        it('@visual: check appearance of basic header workflow', () => {
            cy.get('.nav-main-toggle').should('not.be.visible');

            // Take snapshot for visual testing
            cy.takeSnapshot('Header on deskop', '.nav.main-navigation-menu', { widths: [375, 1920] });
        });
    });

    context('iphone-6 resolution', () => {
        beforeEach(() => {
            // run these tests as if in a mobile browser
            // and ensure our responsive UI is correct
            cy.viewport('iphone-6');
        });

        it('@visual: check appearance of mobile menu workflow', () => {
            cy.get('.nav.main-navigation-menu').should('not.be.visible');
            cy.get('.header-main .menu-button .nav-main-toggle-btn').should('be.visible').click();

            // Take snapshot for visual testing
            cy.takeSnapshot('Mobile menu', '.offcanvas.is-left.is-open', { widths: [375, 1920] });
        });
    });
});
