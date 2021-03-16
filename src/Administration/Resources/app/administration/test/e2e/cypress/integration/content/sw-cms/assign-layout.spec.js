// / <reference types="Cypress" />

describe('CMS: Test assignment of layouts to categories and shop pages', () => {
    beforeEach(() => {
        let salesChannel;
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                });
            })
            .then((data) => {
                salesChannel = data.id;
                return cy.createCmsFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('cms-page', {}, 'cms-page-shop-page');
            })
            .then(() => {
                return cy.createDefaultFixture('cms-page', { name: 'Testing page', type: 'landingpage' }, 'cms-page-shop-page');
            })
            .then((page) => {
                page.name = 'Initial Page';
                page.type = 'landingpage';

                return cy.createDefaultFixture('landing-page', {
                    cmsPage: page,
                    salesChannels: [
                        {
                            id: salesChannel
                        }
                    ]
                });
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            });
    });

    it('@base @content: assign layout to landing page from layout editor', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        // Go to detail view
        cy.get('.sw-cms-list-item--1').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Open layout assignment from sidebar
        cy.get('.sw-sidebar-navigation-item[title="Layout assignment"]').click();
        cy.get('.sw-cms-sidebar__layout-assignment-content').should('be.visible');
        cy.get('.sw-cms-sidebar__layout-assignment-open').click();

        // Layout assignment modal should be visible
        cy.get('.sw-cms-layout-assignment-modal').should('be.visible');
        cy.get('.sw-cms-layout-assignment-modal__tabs').should('be.visible');

        // Navigate to shop pages tab
        cy.get('.sw-cms-layout-assignment-modal__tab-landing-pages').click();

        // Assign landing page
        cy.get('.sw-cms-layout-assignment-modal__landing-page-select').typeMultiSelectAndCheck('Testingpage');

        // Confirm layout assignment
        cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();

        // Warning modal should appear because landing page has an assigned layout
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('be.visible');
        cy.get('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').should('be.visible').contains('landing pages');

        // Confirm changes
        cy.get('.sw-cms-layout-assignment-modal__action-changes-confirm').click();

        // Assignment modal should disappear
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('not.be.visible');
        cy.get('.sw-cms-layout-assignment-modal').should('not.be.visible');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains landingPages
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            expect(xhr.request.body).to.have.property('landingPages');
        });


        // Collapse category and expand landing page tree
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Verify layout is assigned to landing page
        cy.get('.sw-tree-item__element').contains('Testingpage').click();
        cy.get('.sw-landing-page-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-layout-card__desc-headline').contains('Testing page');

        // Verify layout in storefront
        cy.visit('/landingpage');
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');
    });

    it('@base @content: assign layout to category from layout editor', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        // Go to detail view
        cy.get('.sw-cms-list-item--3').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Open layout assignment from sidebar
        cy.get('.sw-sidebar-navigation-item[title="Layout assignment"]').click();
        cy.get('.sw-cms-sidebar__layout-assignment-content').should('be.visible');
        cy.get('.sw-cms-sidebar__layout-assignment-open').click();

        // Layout assignment modal should be visible
        cy.get('.sw-cms-layout-assignment-modal').should('be.visible');

        // Assign root category in tree field
        cy.get('.sw-category-tree__input-field').focus();
        cy.get('.sw-category-tree-field__results').should('be.visible');
        cy.get('.sw-tree-item__element').contains('Home').parent().parent()
            .find('.sw-field__checkbox')
            .click();
        cy.get('.sw-category-tree-field__selected-label').contains('Home').should('be.visible');

        // Confirm layout assignment
        cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();

        // Warning modal should appear because root category has an assigned layout
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('be.visible');
        cy.get('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').should('be.visible');

        // Confirm changes
        cy.get('.sw-cms-layout-assignment-modal__action-changes-confirm').click();

        // Both modals should disappear
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('not.be.visible');
        cy.get('.sw-cms-layout-assignment-modal').should('not.be.visible');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains categories
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            expect(xhr.request.body).to.have.property('categories');
        });

        // Verify layout is assigned to category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-category-tree__inner .sw-tree-item__element').contains('Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.get('.sw-category-layout-card__desc-headline').contains('Vierte Wand');

        // Verify layout in storefront
        cy.visit('/');
        cy.get('.cms-block h2').contains('Lorem Ipsum dolor sit amet');
    });

    it('@base @content: assign layout to shop page from layout editor', () => {
        cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'patch'
        }).as('saveData');

        // cy.server();
        cy.route({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'post'
        }).as('saveShopPageData');

        // Go to detail view
        cy.get('.sw-cms-list-item--2').click();
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add simple text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.get('.sw-text-editor__content-editor h2').contains('Lorem Ipsum dolor sit amet');

        // Open layout assignment from sidebar
        cy.get('.sw-sidebar-navigation-item[title="Layout assignment"]').click();
        cy.get('.sw-cms-sidebar__layout-assignment-content').should('be.visible');
        cy.get('.sw-cms-sidebar__layout-assignment-open').click();

        // Layout assignment modal should be visible
        cy.get('.sw-cms-layout-assignment-modal').should('be.visible');
        cy.get('.sw-cms-layout-assignment-modal__tabs').should('be.visible');

        // Navigate to shop pages tab
        cy.get('.sw-cms-layout-assignment-modal__tab-shop-pages').click();

        // Fill in shop page
        cy.get('.sw-cms-layout-assignment-modal__sales-channel-select').contains('All Sales Channels');
        cy.get('.sw-cms-layout-assignment-modal__shop-page-select').typeMultiSelectAndCheck('Contact forms');

        // Confirm layout assignment
        cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();

        // Verify shop page request was successful
        cy.wait('@saveShopPageData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
            expect(xhr.request.body.null).to.have.property('core.basicInformation.contactPage');
        });

        cy.get('.sw-cms-layout-assignment-modal').should('not.be.visible');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains categories
        cy.wait('@saveData').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // Navigate to settings basic information
        cy.visit(`${Cypress.env('admin')}#/sw/settings/basic/information/index`);

        // Verify contact page has assigned layout
        cy.get('.sw-system-config--field-core-basic-information-contact-page').scrollIntoView();
        cy.get('.sw-cms-page-select-box[name="core.basicInformation.contactPage"]').contains('Wall of Text');

        // Verify layout in storefront
        cy.visit('/');
        cy.get('.footer-contact-form').scrollIntoView();
        cy.get('.footer-contact-form a[title="contact form"]').click();
        cy.get('.modal .modal-dialog').should('be.visible');
        cy.get('.modal .modal-dialog .modal-body .cms-block h2').contains('Lorem Ipsum dolor sit amet');
    });
});
