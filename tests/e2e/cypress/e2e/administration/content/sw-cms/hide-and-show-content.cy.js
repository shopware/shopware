/**
 * @package content
 */

describe('CMS: Show and hide content', () => {
    beforeEach(() => {
        cy.createCmsFixture().then(() => {
            cy.viewport(1920, 1080);
            cy.openInitialPage(`${Cypress.env('admin')}#/sw/cms/index`);
            cy.get('.sw-skeleton').should('not.exist');
            cy.get('.sw-loader').should('not.exist');
        });
    });

    it('@content: show and hide blocks', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('saveCategory');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Landing page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.contains('.sw-cms-create-wizard__title', 'How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('LDP Layout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add some text blocks
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        cy.get('.sw-cms-stage-add-block:last-child').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-stage-add-block:last-child');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Edit text block
        cy.get('.sw-cms-stage-block')
            .get('.sw-text-editor__content-editor').should('be.visible')
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').first().clear()
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').first().type('This block is only visible in Mobile and Desktop');
        cy.get('.sw-cms-stage-block')
            .get('.sw-text-editor__content-editor').should('be.visible')
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').last().clear()
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').last().type('This block is always visible');

        // Make it invisible in Tablet
        cy.get('.sw-cms-block__config-overlay').first().invoke('show');
        cy.get('.sw-cms-block__config-overlay').first().should('be.visible');
        cy.get('.sw-cms-block__config-overlay').first().click();
        cy.contains('.sw-sidebar-collapse__title', 'Visibility')
            .siblings('.sw-sidebar-collapse__indicator').click();
        cy.get('.sw-cms-visibility-config__checkbox:nth-child(2)').click();
        cy.get('.icon--regular-tablet').click();
        cy.get('.sw-cms-visibility-toggle__button').click();

        cy.get('.sw-cms-detail__save-action').click();

        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Assign new layout to Home page
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);

        cy.contains('.tree-link', 'Home').should('be.visible');
        cy.contains('.tree-link', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.contains('.sw-cms-list-item', 'LDP Layout').click();
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.contains('.sw-category-layout-card__desc-headline', 'LDP Layout').should('be.visible');

        cy.get('.sw-cms-page-form__device-actions').should('be.visible');
        cy.get('.sw-cms-page-form__block-device-actions')
            .first()
            .get('.icon--regular-tablet-slash').should('be.visible');

        cy.get('.sw-button-process__content').click();

        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.cms-block-text.hidden-tablet').first().should('be.visible');

        cy.viewport('ipad-2');
        cy.get('.cms-block-text.hidden-tablet').first().should('not.be.visible');

        cy.viewport('iphone-xr');
        cy.get('.cms-block-text.hidden-tablet').first().should('be.visible');
    });

    it('@content: show and hide sections', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('saveCategory');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Landing page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.contains('.sw-cms-create-wizard__title', 'How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('LDP Layout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add a text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Edit the text block
        cy.get('.sw-cms-stage-block')
            .get('.sw-text-editor__content-editor').should('be.visible')
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').clear()
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').type('This section is only visible in Mobile and Desktop');

        // Make it invisible in Tablet
        cy.get('.sw-cms-section__action').should('be.visible');
        cy.get('.sw-cms-section__action').click();
        cy.contains('.sw-sidebar-collapse__title', 'Visibility')
            .siblings('.sw-sidebar-collapse__indicator').click();
        cy.get('.sw-cms-visibility-config__checkbox:nth-child(2)').click();
        cy.get('.icon--regular-tablet').click();

        cy.get('.sw-cms-visibility-config__checkbox:nth-child(2)')
            .children('.sw-icon ').should('have.class', 'icon--regular-tablet-slash');
        cy.get('.sw-cms-visibility-toggle__button').click();

        // Create another section
        cy.get('.sw-cms-stage-add-section__button').last().click();
        cy.get('.sw-cms-stage-section-selection__default-preview').click();

        // Add a text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Edit the text block
        cy.get('.sw-cms-stage-block')
            .get('.sw-text-editor__content-editor').should('be.visible')
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').last().clear()
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').last().type('This section is always visible');

        cy.get('.sw-cms-detail__save-action').click();

        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Assign new layout to Home page
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);

        cy.contains('.tree-link', 'Home').should('be.visible');
        cy.contains('.tree-link', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.contains('.sw-cms-list-item', 'LDP Layout').click();
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.contains('.sw-category-layout-card__desc-headline', 'LDP Layout').should('be.visible');

        cy.get('.sw-cms-page-form__device-actions').first()
            .children('.sw-icon ').should('have.class', 'icon--regular-tablet-slash');
        cy.get('.sw-cms-page-form__block-device-actions').first()
            .children('.sw-icon ').should('have.class', 'icon--regular-tablet-slash');

        cy.get('.sw-button-process__content').click();

        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 200);

        cy.visit('/');
        cy.get('.cms-section').first().should('have.class', 'hidden-tablet');
        cy.get('.cms-section.hidden-tablet').should('be.visible');

        cy.viewport('ipad-2');
        cy.get('.cms-section.hidden-tablet').should('not.be.visible');

        cy.viewport('iphone-xr');
        cy.get('.cms-section.hidden-tablet').should('be.visible');
    });

    it('@content: show the notification', { tags: ['pa-content-management'] }, () => {
        cy.intercept({
            url: `${Cypress.env('apiPath')}/cms-page`,
            method: 'POST',
        }).as('saveData');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/category`,
            method: 'POST',
        }).as('saveCategory');

        // Fill in basic data
        cy.contains('Create new layout').click();
        cy.get('.sw-cms-detail').should('be.visible');
        cy.contains('.sw-cms-create-wizard__page-type', 'Landing page').click();
        cy.contains('.sw-cms-create-wizard__title', 'Choose a section type to start with.');
        cy.contains('.sw-cms-stage-section-selection__default', 'Full width').click();
        cy.contains('.sw-cms-create-wizard__title', 'How do you want to label your new layout?');
        cy.contains('.sw-button--primary', 'Create layout').should('not.be.enabled');
        cy.get('#sw-field--page-name').typeAndCheck('LDP Layout');
        cy.contains('.sw-button--primary', 'Create layout').should('be.enabled');
        cy.contains('.sw-button--primary', 'Create layout').click();
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-cms-section__empty-stage').should('be.visible');

        // Add a text block
        cy.get('.sw-cms-section__empty-stage').click();
        cy.get('.sw-cms-sidebar__block-preview')
            .first()
            .dragTo('.sw-cms-section__empty-stage');
        cy.get('.sw-cms-block').should('be.visible');
        cy.contains('.sw-text-editor__content-editor h2', 'Lorem Ipsum dolor sit amet');

        // Edit the text block
        cy.get('.sw-cms-stage-block')
            .get('.sw-text-editor__content-editor').should('be.visible')
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').clear()
            .get('.sw-cms-slot:nth-of-type(1) .sw-text-editor__content-editor').type('This section is only visible in Mobile and Desktop');

        // Make it invisible in Tablet
        cy.get('.sw-cms-section__action').should('be.visible');
        cy.get('.sw-cms-section__action').click();
        cy.contains('.sw-sidebar-collapse__title', 'Visibility')
            .siblings('.sw-sidebar-collapse__indicator').click();
        cy.get('.sw-cms-visibility-config__checkbox:nth-child(1)').click();
        cy.get('.icon--regular-mobile').click();
        cy.get('.sw-cms-visibility-config__checkbox:nth-child(2)').click();
        cy.get('.icon--regular-tablet').click();
        cy.get('.sw-cms-visibility-config__checkbox:nth-child(3)').click();
        cy.get('.icon--regular-desktop').click();

        cy.get('.sw-cms-visibility-toggle__button').click();

        cy.get('.sw-cms-detail__save-action').click();

        cy.wait('@saveData')
            .its('response.statusCode').should('equal', 204);

        // Assign new layout to Home page
        cy.visit(`${Cypress.env('admin')}#/sw/category/index`);

        cy.contains('.tree-link', 'Home').should('be.visible');
        cy.contains('.tree-link', 'Home').click();
        cy.get('.sw-category-detail__tab-cms').click();
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.contains('.sw-cms-list-item', 'LDP Layout').click();
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        cy.contains('.sw-category-layout-card__desc-headline', 'LDP Layout').should('be.visible');

        cy.get('.sw-cms-page-form__element-config')
            .children('.sw-alert ')
            .contains('This block is not visible on any device. You can edit the block but the changes will not be visible in your shop. Edit this layout in shopping experiences module to change the block visibility');

        cy.get('.sw-button-process__content').click();
        cy.wait('@saveCategory')
            .its('response.statusCode').should('equal', 200);
    });
});
