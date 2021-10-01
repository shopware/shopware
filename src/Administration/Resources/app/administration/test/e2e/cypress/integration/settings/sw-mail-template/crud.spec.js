// / <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Mail templates: Test crud privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: create and read email template', () => {
        const page = new SettingsPageObject();

        // prepare api to create a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-template`,
            method: 'POST'
        }).as('createMailTemplate');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // create mail template
        cy.get('.sw-mail-template__button-create').click();
        cy.get('a[href="#/sw/mail/template/create"]').click();

        // Set update
        cy.get('#mailTemplateTypes').typeSingleSelectAndCheck('Contact form', '#mailTemplateTypes');
        cy.get('#sw-field--mailTemplate-description').typeAndCheck('Get feedback');
        cy.get('#sw-field--mailTemplate-subject').typeAndCheck('Your feedback is sent successfully');
        cy.get('#sw-field--mailTemplate-senderName').typeAndCheck('Demoshop');
        cy.get('div[name="content_plain"]').type('Successful');
        cy.get('div[name="content_html"]').type('Successful');

        // do saving action
        cy.get(page.elements.mailTemplateSaveAction).click();

        // call api to update the mail template
        cy.wait('@createMailTemplate').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // check if user is no index page
        cy.url().should('contain', `${Cypress.config().baseUrl}/admin#/sw/mail/template/index`);

        // filter mail templates by their type
        cy.get(page.elements.smartBarSearch).typeAndCheck('Contact form');

        // wait for filtered mail template result to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);

        // prove that only two mail templates exist with the type 'Contact form'
        cy.get(page.elements.mailTemplateGridList)
            .find(`tbody ${page.elements.dataGridRow}`)
            .should('have.length', 2);

        // verify correct mail template type of newly created template
        cy.get(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--mailTemplateType-name`)
            .contains('Contact form');

        // verify correct description type of newly created template
        cy.get(`${page.elements.dataGridRow}--1 ${page.elements.dataGridColumn}--description`)
            .contains('Get feedback');
    });

    it('@settings: edit email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-template/*`,
            method: 'PATCH'
        }).as('saveMailTemplate');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // filter for updated email template
        cy.get(page.elements.smartBarSearch)
            .typeAndCheck('Contact form');

        // wait until result of filtered mail templates has been loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`${page.elements.dataGridRow}--1`).should('not.exist');
        cy.get(`${page.elements.dataGridRow}--0`).should('be.visible');

        // open email template
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);

        // update fields
        cy.get('#sw-field--mailTemplate-description').should('have.value', 'Contact form received');
        cy.get('#sw-field--mailTemplate-description').clearTypeAndCheck('Custom description');
        cy.get('#sw-field--mailTemplate-subject').clearTypeAndCheck('Subject');
        cy.get('#sw-field--mailTemplate-senderName').clearTypeAndCheck('DemoShop');

        // do saving action
        cy.get(page.elements.mailTemplateSaveAction).click();

        // call api to update the mail template
        cy.wait('@saveMailTemplate').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // filter for updated email template
        cy.get(page.elements.smartBarSearch)
            .typeAndCheck('Contact form');

        // wait until result of filtered mail templates has been loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);

        // verify fields
        cy.get('.sw-data-grid-skeleton').should('not.exist');
        cy.get(`#mailTemplateGrid ${page.elements.dataGridRow}--1`).should('not.exist');
        cy.contains(`#mailTemplateGrid ${page.elements.dataGridRow}--0`, 'Custom description')
            .should('be.visible');
    });

    it('@settings: delete email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-template/*`,
            method: 'delete'
        }).as('deleteMailTemplate');

        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for mail templates to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);

        // filter for deleted mail template
        cy.get(page.elements.smartBarSearch).typeAndCheck('Contact form');

        // wait for filtered mail templates to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200); cy.get(`${page.elements.dataGridRow}--0 ${page.elements.mailTemplateColumnDescription}`)
            .contains('Contact form received');

        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        cy.get(page.elements.modal).should('not.exist');

        // call api to delete mail template
        cy.wait('@deleteMailTemplate').its('response.statusCode').should('equal', 204);// check if empty state if visible
        cy.get(':nth-child(1) .sw-card__content .sw-empty-state')
            .should('be.visible');
    });

    it('@settings: duplicate email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-template/*`,
            method: 'PATCH'
        }).as('saveMailTemplate');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/mail-template/*`,
            method: 'POST'
        }).as('cloneMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// filter for cloned mail template
        cy.get(page.elements.smartBarSearch).typeAndCheck('Contact form');

        // wait for filtered mail templates to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200); cy.clickContextMenuItem(
            '.sw-mail-template-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait('@cloneMailTemplate').its('response.statusCode').should('equal', 200); cy.get('#sw-field--mailTemplate-description').clearTypeAndCheck('Duplicated description');

        // do saving action
        cy.get(page.elements.mailTemplateSaveAction).click();

        // call api to update the mail template
        cy.wait('@saveMailTemplate').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // filter for cloned mail template
        cy.get(page.elements.smartBarSearch).typeAndCheck('Contact form');

        // wait for filtered mail templates to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// prove that only two mail templates exist with the type 'Contact form'
        cy.get(page.elements.mailTemplateGridList)
            .find(`tbody ${page.elements.dataGridRow}`)
            .should('have.length', 2);

        // check description of duplicated mail template
        cy.get(`${page.elements.dataGridRow}--1 ${page.elements.mailTemplateColumnDescription}`)
            .contains('Duplicated description');
    });

    it('@settings: create and read email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to create a mail header footer
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-header-footer`,
            method: 'POST'
        }).as('createMailHeaderFooter');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // create mail header footer
        cy.get('.sw-mail-template__button-create').click();
        cy.get('a[href="#/sw/mail/template/create-head-foot"]').click();

        // Set update
        cy.get('#sw-field--mailHeaderFooter-name').typeAndCheck('Storefront template');
        cy.get('#sw-field--mailHeaderFooter-description').typeAndCheck('Default description');

        // do saving action
        cy.get(page.elements.mailHeaderFooterSaveAction).click();

        // call api to create the mail header footer
        cy.wait('@createMailHeaderFooter').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // filter for mail template footers
        cy.get(page.elements.smartBarSearch).typeAndCheck('Storefront template');

        // wait for filtered mail templates to be loaded
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// prove that only one mail template footer exists
        cy.get(page.elements.mailHeaderFooterGridList)
            .scrollIntoView()
            .find(`tbody ${page.elements.dataGridRow}`)
            .should('have.length', 1);

        // verify fields
        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnName}`)
            .contains('Storefront template');

        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnDescription}`)
            .contains('Default description');
    });

    it('@settings: edit email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-header-footer/*`,
            method: 'PATCH'
        }).as('saveMailHeaderFooter');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-header-footer`,
            method: 'POST'
        }).as('searchMailHeaderFooterTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// open email header footer
        cy.get(page.elements.mailHeaderFooterGridList).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait('@searchMailHeaderFooterTemplate').its('response.statusCode').should('equal', 200);// update fields
        cy.get('#sw-field--mailHeaderFooter-description').clear().type('Edited description');

        cy.get('.sw-mail-header-footer-detail__sales-channel').scrollIntoView();
        cy.get('.sw-mail-header-footer-detail__sales-channel').typeMultiSelectAndCheck('Storefront');
        cy.get('.sw-mail-header-footer-detail__sales-channel .sw-select-selection-list__input')
            .type('{esc}');

        // do saving action
        cy.get(page.elements.mailHeaderFooterSaveAction).click();

        // call api to update the mail header footer
        cy.wait('@saveMailHeaderFooter').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // verify fields
        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnDescription}`)
            .scrollIntoView()
            .contains('Edited description');

        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnSalesChannel}`)
            .contains('Storefront');
    });

    it('@settings: delete email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-header-footer/*`,
            method: 'delete'
        }).as('deleteMailHeaderFooter');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// scroll to email header footer
        cy.get('.sw-empty-state__title').should('not.exist');
        cy.get(page.elements.mailHeaderFooterGridList).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-context-menu-item--danger',
            page.elements.contextMenuButton,
            `${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0`
        );

        cy.get('.sw-modal__body').should('be.visible');
        cy.get('.sw-modal__body')
            .contains('Are you sure you want to delete this item?');
        cy.get(`${page.elements.modal}__footer button${page.elements.dangerButton}`).click();

        cy.get(page.elements.modal).should('not.exist');

        // call api to delete the mail header footer
        cy.wait('@deleteMailHeaderFooter').its('response.statusCode').should('equal', 204);// eslint-disable-next-line max-len
        cy.get(':nth-child(1) .sw-card__content .sw-empty-state')
            .should('be.visible');
    });

    it('@settings: duplicate email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.intercept({
            url: `${Cypress.env('apiPath')}/mail-header-footer/*`,
            method: 'PATCH'
        }).as('saveMailHeaderFooter');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/search/mail-template`,
            method: 'POST'
        }).as('searchMailTemplate');
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/clone/mail-header-footer/*`,
            method: 'POST'
        }).as('cloneMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait('@searchMailTemplate').its('response.statusCode').should('equal', 200);// scroll to email header footer
        cy.get('.sw-empty-state__title').should('not.exist');
        cy.get(page.elements.mailHeaderFooterGridList).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-mail-header-footer-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait('@cloneMailTemplate').its('response.statusCode').should('equal', 200); cy.get('#sw-field--mailHeaderFooter-description').clearTypeAndCheck('Duplicated description');

        // do saving action
        cy.get(page.elements.mailHeaderFooterSaveAction).click();

        // call api to save mail header footer
        cy.wait('@saveMailHeaderFooter').its('response.statusCode').should('equal', 204); cy.get(page.elements.smartBarBack).click();

        // verify fields
        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnName}`)
            .contains('Default email footer');

        // eslint-disable-next-line max-len
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--1 ${page.elements.mailHeaderFooterColumnName}`)
            .contains('Default email footer');
    });
});
