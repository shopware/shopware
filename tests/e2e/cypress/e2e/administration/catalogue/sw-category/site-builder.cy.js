// / <reference types="Cypress" />
/**
 * @package inventory
 */
describe('Category: site builder feature', () => {
    beforeEach(() => {
        cy.createDefaultFixture('cms-page', {}, 'cms-landing-page')
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/category/index`);
                cy.get('.sw-loader').should('not.exist');
            });
    });

    it('@base @catalogue: create a subcategory as entry point with main navigation', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept('POST', `${Cypress.env('apiPath')}/category`).as('saveCategory');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/category`).as('loadCategory');
        cy.intercept('PATCH', `${Cypress.env('apiPath')}/category/**`).as('updateCategory');

        cy.log('create category');
        cy.get('.sw-category-tree__inner .sw-tree-item:nth-of-type(1) .sw-context-button__button').click();
        cy.get('.sw-context-menu-item.sw-tree-item__sub-action').click();
        cy.get('.sw-category-tree__inner .sw-tree-item__content input').type('Categorian{enter}');
        cy.wait('@saveCategory');
        cy.contains('.sw-category-tree__inner .tree-link', 'Categorian').click();
        cy.wait('@loadCategory');

        cy.log('input category data');
        cy.get('input[name="categoryActive"]').check();
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Page / List', '.sw-category-detail-base__type-selection');
        cy.get('.sw-category-entry-point-card__entry-point-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Main navigation', '.sw-category-entry-point-card__entry-point-selection');

        cy.get('.sw-category-entry-point-card__sales-channel-selection').should('be.visible');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .typeMultiSelectAndCheckMultiple(['Storefront', 'Headless']);

        cy.log('input configure home modal data (Storefront)');
        cy.get('.sw-category-entry-point-card__button-configure-home').click();
        cy.get('.sw-category-entry-point-modal__sales-channel-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Storefront', '.sw-category-entry-point-modal__sales-channel-selection');
        cy.get('.sw-category-entry-point-modal__name-in-main-navigation input').typeAndCheck('StorefrontNameInMainNavigation');
        cy.get('.sw-category-detail-layout__change-layout-action').click();
        cy.get('.sw-cms-layout-modal__content-item--0 input[type="checkbox"]').click();
        cy.contains('.sw-cms-layout-modal .sw-button', 'Save').click();
        cy.get('.sw-category-entry-point-modal__meta-title input').typeAndCheck('StorefrontMetaTitle');
        cy.get('.sw-category-entry-point-modal__meta-description textarea').typeAndCheck('StorefrontMetaDescription');
        cy.get('.sw-category-entry-point-modal__seo-keywords input').typeAndCheck('Storefront, Some, Seo, Keywords');

        cy.log('input configure home modal data (Headless)');
        cy.get('.sw-category-entry-point-modal__sales-channel-selection .sw-block-field__block')
            .scrollIntoView()
            .typeSingleSelectAndCheck('Headless', '.sw-category-entry-point-modal__sales-channel-selection');
        cy.get('.sw-category-entry-point-modal__name-in-main-navigation input').typeAndCheck('HeadlessNameInMainNavigation');

        cy.log('close configure home modal');
        cy.get('.sw-category-entry-point-modal__apply-button').click();

        cy.log('save');
        cy.get('.sw-category-detail__save-action').click();
        cy.get('.sw-category-entry-point-overwrite-modal')
            .should('contain', 'You already assigned entry points to the following Sales Channels:')
            .should('contain', 'Storefront')
            .should('contain', 'Headless');
        cy.get('.sw-confirm-modal__button-confirm').click();

        cy.log('wait for the loading state to finish');
        cy.wait('@updateCategory');

        cy.log('validate changes');
        cy.get('input[name="categoryActive"]').should('be.checked');
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block .sw-select__selection').should('contain', 'Page / List');
        cy.get('.sw-category-entry-point-card__entry-point-selection')
            .should('have.class', 'is--disabled');
        cy.contains('.sw-category-entry-point-card__navigation-entry', 'Storefront')
            .scrollIntoView();

        cy.contains('.sw-category-entry-point-card__navigation-entry', 'Storefront')
            .should('be.visible');

        cy.contains('.sw-category-entry-point-card__navigation-entry', 'Headless')
            .scrollIntoView();

        cy.contains('.sw-category-entry-point-card__navigation-entry', 'Headless')
            .should('be.visible');

        cy.log('validate configure home modal changes (Storefront)');
        cy.get('.sw-category-entry-point-card__button-configure-home').click();
        cy.get('.sw-category-entry-point-modal__sales-channel-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Storefront', '.sw-category-entry-point-modal__sales-channel-selection');
        cy.get('.sw-category-entry-point-modal__name-in-main-navigation input').should('have.value', 'StorefrontNameInMainNavigation');
        cy.get('.sw-category-entry-point-modal__desc-headline').should('contain', 'Baumhaus');
        cy.get('.sw-category-entry-point-modal__meta-title input').should('have.value', 'StorefrontMetaTitle');
        cy.get('.sw-category-entry-point-modal__meta-description textarea').should('have.value', 'StorefrontMetaDescription');
        cy.get('.sw-category-entry-point-modal__seo-keywords input').should('have.value', 'Storefront, Some, Seo, Keywords');

        cy.log('validate configure home modal changes (Headless)');
        cy.get('.sw-category-entry-point-modal__sales-channel-selection .sw-block-field__block')
            .scrollIntoView()
            .typeSingleSelectAndCheck('Headless', '.sw-category-entry-point-modal__sales-channel-selection');
        cy.get('.sw-category-entry-point-modal__name-in-main-navigation input').should('have.value', 'HeadlessNameInMainNavigation');
        cy.get('.sw-category-entry-point-modal__desc-headline').should('have.class', 'is--empty');
        cy.get('.sw-category-entry-point-modal__meta-title input').should('be.empty');
        cy.get('.sw-category-entry-point-modal__meta-description textarea').should('be.empty');
        cy.get('.sw-category-entry-point-modal__seo-keywords input').should('be.empty');
        cy.get('.sw-category-entry-point-modal__cancel-button').click();

        cy.log('validate association in sales channel module');
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront').click();
        cy.get('.sw-sales-channel-detail__select-navigation-category-id').should('contain', 'Categorian');

        cy.log('validate storefront');
        cy.visit('/');
        cy.contains('.main-navigation-menu .nav-link', 'StorefrontNameInMainNavigation').should('be.visible');
        cy.get('head title').should('contain', 'StorefrontMetaTitle');
        cy.get('head meta[name="description"]').should('have.attr', 'content', 'StorefrontMetaDescription');
        cy.get('head meta[name="keywords"]').should('have.attr', 'content', 'Storefront, Some, Seo, Keywords');
        cy.contains('.cms-page', 'Baumhaus landing page').should('be.visible');
    });

    it('@base @catalogue: create a subcategory as entry point with footer navigation', { tags: ['pa-inventory','VUE3'] }, () => {
        cy.intercept('POST', `${Cypress.env('apiPath')}/category`).as('saveCategory');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/category`).as('loadCategory');
        cy.intercept('PATCH', `${Cypress.env('apiPath')}/category/**`).as('updateCategory');

        cy.log('create category');
        cy.get('.sw-category-tree__inner .sw-tree-item:nth-of-type(1) .sw-context-button__button').click();
        cy.get('.sw-context-menu-item.sw-tree-item__sub-action').click();
        cy.get('.sw-category-tree__inner .sw-tree-item__content input').type('Categorian{enter}');
        cy.wait('@saveCategory');
        cy.contains('.sw-category-tree__inner .tree-link', 'Categorian').click();
        cy.wait('@loadCategory');

        cy.log('input category data');
        cy.get('input[name="categoryActive"]').check();
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Page / List', '.sw-category-detail-base__type-selection');
        cy.get('.sw-category-entry-point-card__entry-point-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Footer navigation', '.sw-category-entry-point-card__entry-point-selection');

        cy.get('.sw-category-entry-point-card__sales-channel-selection').should('be.visible');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .typeMultiSelectAndCheckMultiple(['Storefront', 'Headless']);

        cy.log('save and wait for the loading state to finish');
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@updateCategory');

        cy.log('validate changes');
        cy.get('input[name="categoryActive"]').should('be.checked');
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block .sw-select__selection').should('contain', 'Page / List');
        cy.get('.sw-category-entry-point-card__entry-point-selection')
            .should('contain', 'Footer navigation');

        cy.get('.sw-category-entry-point-card__sales-channel-selection').should('be.visible');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .should('contain', 'Storefront')
            .should('contain', 'Headless');

        cy.log('validate association in sales channel module');
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront').click();
        cy.get('.sw-sales-channel-detail__select-footer-category-id').should('contain', 'Categorian');
    });

    it('@base @catalogue: create a subcategory as entry point with footer service navigation', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept('POST', `${Cypress.env('apiPath')}/category`).as('saveCategory');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/category`).as('loadCategory');
        cy.intercept('PATCH', `${Cypress.env('apiPath')}/category/**`).as('updateCategory');

        cy.log('create category');
        cy.get('.sw-category-tree__inner .sw-tree-item:nth-of-type(1) .sw-context-button__button').click();
        cy.get('.sw-context-menu-item.sw-tree-item__sub-action').click();
        cy.get('.sw-category-tree__inner .sw-tree-item__content input').type('Categorian{enter}');
        cy.wait('@saveCategory');
        cy.contains('.sw-category-tree__inner .tree-link', 'Categorian').click();
        cy.wait('@loadCategory');

        cy.log('input category data');
        cy.get('input[name="categoryActive"]').check();
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Page / List', '.sw-category-detail-base__type-selection');
        cy.get('.sw-category-entry-point-card__entry-point-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Footer service navigation', '.sw-category-entry-point-card__entry-point-selection');

        cy.get('.sw-category-entry-point-card__sales-channel-selection').should('be.visible');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .typeMultiSelectAndCheckMultiple(['Storefront', 'Headless']);

        cy.log('save and wait for the loading state to finish');
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@updateCategory');

        cy.log('validate changes');
        cy.get('input[name="categoryActive"]').should('be.checked');
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block .sw-select__selection').should('contain', 'Page / List');
        cy.get('.sw-category-entry-point-card__entry-point-selection')
            .should('contain', 'Footer service navigation');

        cy.get('.sw-category-entry-point-card__sales-channel-selection').should('be.visible');
        cy.get('.sw-category-entry-point-card__sales-channel-selection')
            .should('contain', 'Storefront')
            .should('contain', 'Headless');

        cy.log('validate association in sales channel module');
        cy.contains('.sw-admin-menu__navigation-link', 'Storefront').click();
        cy.get('.sw-sales-channel-detail__select-service-category-id').should('contain', 'Categorian');
    });

    it('@base @catalogue: create a subcategory as internal link to the main category', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept('POST', `${Cypress.env('apiPath')}/category`).as('saveCategory');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/category`).as('loadCategory');
        cy.intercept('PATCH', `${Cypress.env('apiPath')}/category/**`).as('updateCategory');

        cy.log('create category');
        cy.get('.sw-category-tree__inner .sw-tree-item:nth-of-type(1) .sw-context-button__button').click();
        cy.get('.sw-context-menu-item.sw-tree-item__sub-action').click();
        cy.get('.sw-category-tree__inner .sw-tree-item__content input').type('Categorian{enter}');
        cy.wait('@saveCategory');
        cy.contains('.sw-category-tree__inner .tree-link', 'Categorian').click();
        cy.wait('@loadCategory');

        cy.log('input category data');
        cy.get('input[name="categoryActive"]').check();
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Link', '.sw-category-detail-base__type-selection');
        cy.get('.sw-category-link-settings__type .sw-block-field__block')
            .typeSingleSelectAndCheck('Internal', '.sw-category-link-settings__type');
        cy.get('.sw-category-link-settings__entity .sw-block-field__block')
            .typeSingleSelectAndCheck('Category', '.sw-category-link-settings__entity');
        cy.get('.sw-category-link-settings__selection-category .sw-block-field__block .sw-category-tree__input-field').click();
        cy.get('.sw-category-tree-field__results_popover .sw-tree__content').contains('.sw-tree-item__element', 'Home').find('.sw-field__checkbox input').click({force: true});

        cy.log('save and wait for the loading state to finish');
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@updateCategory');

        cy.log('validate changes');
        cy.get('input[name="categoryActive"]').should('be.checked');
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block .sw-select__selection').should('contain', 'Link');
        cy.get('.sw-category-link-settings__type')
            .should('contain', 'Internal');
        cy.get('.sw-category-link-settings__entity')
            .should('contain', 'Category');
        cy.get('.sw-category-link-settings__selection-category')
            .should('contain', 'Home');

        cy.log('validate storefront');
        cy.visit('/');
        cy.contains('.main-navigation-link', 'Categorian').click();
        cy.url().should('be.eq', `${Cypress.config('baseUrl')}/`);
    });

    it('@base @catalogue: create a subcategory as external link in new tab', { tags: ['pa-inventory', 'VUE3'] }, () => {
        cy.intercept('POST', `${Cypress.env('apiPath')}/category`).as('saveCategory');
        cy.intercept('POST', `${Cypress.env('apiPath')}/search/category`).as('loadCategory');
        cy.intercept('PATCH', `${Cypress.env('apiPath')}/category/**`).as('updateCategory');

        cy.log('create category');
        cy.get('.sw-category-tree__inner .sw-tree-item:nth-of-type(1) .sw-context-button__button').click();
        cy.get('.sw-context-menu-item.sw-tree-item__sub-action').click();
        cy.get('.sw-category-tree__inner .sw-tree-item__content input').type('Categorian{enter}');
        cy.wait('@saveCategory');
        cy.contains('.sw-category-tree__inner .tree-link', 'Categorian').click();
        cy.wait('@loadCategory');

        cy.log('input category data');
        cy.get('input[name="categoryActive"]').check();
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block')
            .typeSingleSelectAndCheck('Link', '.sw-category-detail-base__type-selection');
        cy.get('.sw-category-link-settings__type .sw-block-field__block')
            .typeSingleSelectAndCheck('External', '.sw-category-link-settings__type');
        cy.get('.sw-category-link-settings__external-link input')
            .typeAndCheck('asdf', '.sw-category-link-settings__external-link');
        cy.get('.sw-category-link-settings__link-new-tab input').check();

        cy.log('save and wait for the loading state to finish');
        cy.get('.sw-category-detail__save-action').click();
        cy.wait('@updateCategory');

        cy.log('validate changes');
        cy.get('input[name="categoryActive"]').should('be.checked');
        cy.get('.sw-category-detail-base__type-selection .sw-block-field__block .sw-select__selection').should('contain', 'Link');
        cy.get('.sw-category-link-settings__type')
            .should('contain', 'External');
        cy.get('.sw-category-link-settings__external-link input')
            .should('have.value', 'asdf');
        cy.get('.sw-category-link-settings__link-new-tab input').should('be.checked');

        cy.log('validate storefront');
        cy.visit('/');
        cy.contains('a.main-navigation-link', 'Categorian')
            .should('have.attr', 'href', 'asdf')
            .should('have.attr', 'target', '_blank');
    });
});
