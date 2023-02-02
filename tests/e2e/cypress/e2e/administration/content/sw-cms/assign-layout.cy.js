/**
 * @package content
 */
// / <reference types="Cypress" />

describe('CMS: Test assignment of layouts to categories and shop pages', () => {
    beforeEach(() => {
        let salesChannel;
        cy.searchViaAdminApi({
            endpoint: 'sales-channel',
            data: {
                field: 'name',
                type: 'equals',
                value: 'Storefront',
            },
        }).then((data) => {
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
                            id: salesChannel,
                        },
                    ],
                });
            })
            .then(() => {
                cy.viewport(1920, 1080);
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
                cy.get('.sw-skeleton').should('not.exist');
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @content: assign layout to landing page from layout editor', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH',
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
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

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
        cy.contains('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts', 'landing pages').should('be.visible');

        // Confirm changes
        cy.get('.sw-cms-layout-assignment-modal__action-changes-confirm').click();

        // Assignment modal should disappear
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('not.exist');
        cy.get('.sw-cms-layout-assignment-modal').should('not.exist');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains landingPages
        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);


        // Collapse category and expand landing page tree
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-category-detail__category-collapse .sw-sidebar-collapse__indicator').click();
        cy.get('.sw-category-detail__landing-page-collapse .sw-sidebar-collapse__indicator').click();

        // Verify layout is assigned to landing page
        cy.contains('.sw-tree-item__element', 'Testingpage').click();
        cy.get('.sw-landing-page-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.contains('.sw-category-layout-card__desc-headline', 'Testing page');

        // Verify layout in storefront
        cy.visit('/landingpage');
        cy.contains('.cms-block h2', 'Lorem Ipsum dolor sit amet');
    });

    it('@base @content: assign layout to category from layout editor', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH',
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
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Open layout assignment from sidebar
        cy.get('.sw-sidebar-navigation-item[title="Layout assignment"]').click();
        cy.get('.sw-cms-sidebar__layout-assignment-content').should('be.visible');
        cy.get('.sw-cms-sidebar__layout-assignment-open').click();

        // Layout assignment modal should be visible
        cy.get('.sw-cms-layout-assignment-modal').should('be.visible');

        // Assign root category in tree field
        cy.get('.sw-category-tree__input-field').focus();
        cy.get('.sw-category-tree-field__results_popover').should('be.visible');
        cy.contains('.sw-tree-item__element', 'Home').parent().parent()
            .find('.sw-field__checkbox')
            .click();
        cy.get('.sw-modal__title').click();
        cy.contains('.sw-category-tree-field__selected-label', 'Home').should('be.visible');

        // Confirm layout assignment
        cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();

        // Warning modal should appear because root category has an assigned layout
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('be.visible');
        cy.get('.sw-cms-layout-assignment-modal__confirm-text-assigned-layouts').should('be.visible');

        // Confirm changes
        cy.get('.sw-cms-layout-assignment-modal__action-changes-confirm').click();

        // Both modals should disappear
        cy.get('.sw-cms-layout-assignment-modal__confirm-changes-modal').should('not.exist');
        cy.get('.sw-cms-layout-assignment-modal').should('not.exist');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains categories
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Verify layout is assigned to category
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');
        cy.contains('.sw-category-tree__inner .sw-tree-item__element', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').scrollIntoView().click();
        cy.get('.sw-card.sw-category-layout-card').scrollIntoView();
        cy.contains('.sw-category-layout-card__desc-headline', 'Vierte Wand');

        // Verify layout in storefront
        cy.visit('/');
        cy.contains('.cms-block h2', 'Lorem Ipsum dolor sit amet');
    });

    it('@base @content: assign layout to shop page from layout editor', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page/*`,
            method: 'PATCH',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/system-config/batch`,
            method: 'POST',
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
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

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
        cy.contains('.sw-cms-layout-assignment-modal__sales-channel-select', 'All Sales Channels');
        cy.get('.sw-cms-layout-assignment-modal__shop-page-select').typeMultiSelectAndCheck('Contact forms');

        // Confirm layout assignment
        cy.get('.sw-cms-layout-assignment-modal__action-confirm').click();

        // Verify shop page request was successful
        cy.wait('@saveShopPageData').its('response.statusCode').should('equal', 204);

        cy.get('.sw-cms-layout-assignment-modal').should('not.exist');

        // Save the layout
        cy.get('.sw-cms-detail__save-action').click();

        // Verify request is successful and contains categories
        cy.wait('@saveData').its('response.statusCode').should('equal', 204);

        // Navigate to settings basic information
        cy.visit(`${Cypress.env('admin')}#/sw/settings/basic/information/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        // Verify contact page has assigned layout
        cy.get('.sw-system-config--field-core-basic-information-contact-page').scrollIntoView();
        cy.contains('.sw-cms-page-select-box[name="core.basicInformation.contactPage"]', 'Wall of Text');

        // Verify layout in storefront
        cy.visit('/');
        cy.get('.footer-contact-form').scrollIntoView();
        cy.get('.footer-contact-form a[title="Contact form"]').click();
        cy.get('.modal .modal-dialog').should('be.visible');
        cy.contains('.modal .modal-dialog .modal-body .cms-block h2', 'Lorem Ipsum dolor sit amet');
    });
});
