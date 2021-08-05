// TODO See NEXT-6902: Use an own storefront project or make E2E tests independent from bundle
describe('Header menu:Visual tests', () => {
    beforeEach(() => {
        cy.visit('/');
    });

    // eslint-disable-next-line no-undef
    context('720p resolution', () => {
        beforeEach(() => {
            // run these tests as if in a desktop
            // browser with a 720p monitor
            cy.viewport(1280, 720);
        });

        it('@visual: check appearance of basic header workflow', () => {
            cy.get('.nav-main-toggle').should('not.be.visible');

            // Take snapshot for visual testing
            cy.takeSnapshot('[Header] Deskop', '.nav.main-navigation-menu');
        });
    });

    // eslint-disable-next-line no-undef
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
            cy.takeSnapshot('[Header] Mobile menu', '.offcanvas.is-left.is-open', { widths: [375] });
        });
    });
});
