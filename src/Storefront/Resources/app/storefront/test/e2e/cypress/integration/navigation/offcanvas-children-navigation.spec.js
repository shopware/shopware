describe('Offcanvas navigation with regards to children visibility', () => {

    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.searchViaAdminApi({
                    endpoint: 'category',
                    data: {
                        field: 'name',
                        value: 'Home'
                    }
                }).then(({id: categoryId}) => {
                    cy.createCategoryFixture({
                        name: 'Visible children',
                        type: 'folder',
                        parentId: categoryId,
                        children: [
                            {
                                name: 'Visible grandchildren',
                                type: 'page',
                                children: [
                                    {
                                        name: 'Visible',
                                        type: 'page'
                                    }
                                ]
                            },
                            {
                                name: 'Invisible grandchildren',
                                type: 'page',
                                children: [
                                    {
                                        name: 'Invisible',
                                        type: 'page',
                                        visible: false
                                    }
                                ]
                            }
                        ]
                    });

                    cy.createCategoryFixture({
                        name: 'Invisible children',
                        type: 'page',
                        parentId: categoryId,
                        children: [
                            {
                                name: 'Invisible',
                                type: 'page',
                                visible: false
                            }
                        ]
                    });
                });
            }).then(() => {
                cy.visit('/');
            });
    });

    context('iphone-6 resolution', () => {
        beforeEach(() => {
            // run these tests as if in a mobile browser
            // and ensure our responsive UI is correct
            cy.viewport('iphone-6');
        });

        it('@navigation: Check menu entries for visible children', () => {
            cy.server();
            cy.route({
                url: '/widgets/menu/offcanvas*',
                method: 'GET'
            }).as('offcanvasMenuRequest');

            cy.get('.nav.main-navigation-menu').should('not.be.visible');
            cy.get('.header-main .menu-button .nav-main-toggle-btn').should('be.visible').click();

            // test that menu entries are visible
            cy.get('.offcanvas .nav-item.nav-link').contains('Visible children')
                .should('be.visible');
            cy.get('.offcanvas .nav-item.nav-link').contains('Invisible children')
                .should('be.visible');

            // test menu entries are marked correctly with and without visible children
            cy.get('.offcanvas .nav-item.nav-link').contains('Visible children').parent()
                .should('have.class', 'js-navigation-offcanvas-link');
            cy.get('.offcanvas .nav-item.nav-link').contains('Invisible children').parent()
                .should('not.have.class', 'js-navigation-offcanvas-link');

            // navigate to menu entry with visible children
            cy.get('.offcanvas .nav-item.nav-link').contains('Visible children').click({ force: true });
            cy.wait('@offcanvasMenuRequest');

            // test that children are marked correctly with and without visible grandchildren
            cy.get('.offcanvas .nav-item.nav-link').contains('Visible grandchildren').parent()
                .should('have.class', 'js-navigation-offcanvas-link');
            cy.get('.offcanvas .nav-item.nav-link').contains('Invisible grandchildren').parent()
                .should('not.have.class', 'js-navigation-offcanvas-link');
        });
    });
});
