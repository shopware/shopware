/// <reference types="Cypress" />

import SettingsPageObject from '../../../support/pages/module/sw-settings.page-object';

describe('Mail templates: Test crud privileges', () => {
    beforeEach(() => {
        cy.setToInitialState()
            .then(() => {
                cy.loginViaApi();
            })
            .then(() => {
                return cy.createDefaultFixture('mail-header-footer');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/dashboard/index`);
            });
    });

    it('@settings: create and read email template', () => {
        const page = new SettingsPageObject();

        // prepare api to create a mail template
        cy.server();
        cy.route({
            url: '/api/v*/mail-template',
            method: 'post'
        }).as('createMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // create mail template
        cy.get('.sw-mail-template__button-create').click();
        cy.get('a[href="#/sw/mail/template/create"]').click();

        // wait for data loading
        cy.wait(3000);

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
        cy.wait('@createMailTemplate').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // TODO: verify fields will do when NEXT-7072 search function is fixed
    });

    it('@settings: edit email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();
        cy.route({
            url: '/api/v*/mail-template/*',
            method: 'patch'
        }).as('saveMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // open email template
        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait(3000);

        // update fields
        cy.get('#sw-field--mailTemplate-description').clear().type('Default description');
        cy.get('#sw-field--mailTemplate-subject').clear().type('Subject');
        cy.get('#sw-field--mailTemplate-senderName').clear().type('DemoShop');

        // do saving action
        cy.get(page.elements.mailTemplateSaveAction).click();

        // call api to update the mail template
        cy.wait('@saveMailTemplate').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // verify fields
        cy.get(`${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailTemplateColumnDescription}`)
            .contains('Default description');
    });

    it('@settings: delete email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();
        cy.route({
            url: '/api/v*/mail-template/*',
            method: 'delete'
        }).as('deleteMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

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
        cy.wait('@deleteMailTemplate').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        // TODO: verify fields will do when NEXT-7072 search function is fixed
    });

    it('@settings: duplicate email template', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();

        cy.route({
            url: '/api/v*/mail-template/*',
            method: 'patch'
        }).as('saveMailTemplate');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        cy.clickContextMenuItem(
            '.sw-mail-template-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.mailTemplateGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait(3000);

        cy.get('#sw-field--mailTemplate-description').clearTypeAndCheck('Duplicated description');

        // do saving action
        cy.get(page.elements.mailTemplateSaveAction).click();

        // call api to update the mail template
        cy.wait('@saveMailTemplate').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // TODO: verify fields will do when NEXT-7072 search function is fixed
    });

    it('@settings: create and read email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to create a mail header footer
        cy.server();
        cy.route({
            url: '/api/v*/mail-header-footer',
            method: 'post'
        }).as('createMailHeaderFooter');

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
        cy.wait('@createMailHeaderFooter').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // TODO: verify fields will do when NEXT-7072 search function is fixed
    });

    it('@settings: edit email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();
        cy.route({
            url: '/api/v*/mail-header-footer/*',
            method: 'patch'
        }).as('saveMailHeaderFooter');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait(3000);

        // open email header footer
        cy.get(page.elements.mailHeaderFooterGridList).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait(3000);

        // update fields
        cy.get('#sw-field--mailHeaderFooter-description').clear().type('Edited description');

        // do saving action
        cy.get(page.elements.mailHeaderFooterSaveAction).click();

        // call api to update the mail header footer
        cy.wait('@saveMailHeaderFooter').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // verify fields
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnDescription}`)
            .contains('Edited description');
    });

    it('@settings: delete email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();
        cy.route({
            url: '/api/v*/mail-header-footer/*',
            method: 'delete'
        }).as('deleteMailHeaderFooter');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait(3000);

        // scroll to email header footer
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
        cy.wait('@deleteMailHeaderFooter').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnName}`).should('not.exist');
    });

    it('@settings: duplicate email header footer', () => {
        const page = new SettingsPageObject();

        // prepare api to update a mail template
        cy.server();

        cy.route({
            url: '/api/v*/mail-header-footer/*',
            method: 'patch'
        }).as('saveMailHeaderFooter');

        // go to mail template module
        cy.get('.sw-admin-menu__item--sw-settings').click();
        cy.get('#sw-mail-template').click();

        // wait for data loading
        cy.wait(3000);

        // scroll to email header footer
        cy.get(page.elements.mailHeaderFooterGridList).scrollIntoView();

        cy.clickContextMenuItem(
            '.sw-mail-header-footer-list-grid__duplicate-action',
            page.elements.contextMenuButton,
            `${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0`
        );

        // wait for data loading
        cy.wait(3000);

        cy.get('#sw-field--mailHeaderFooter-description').clear().type('Duplicated description');

        // do saving action
        cy.get(page.elements.mailHeaderFooterSaveAction).click();

        // call api to save mail header footer
        cy.wait('@saveMailHeaderFooter').then((xhr) => {
            expect(xhr).to.have.property('status', 204);
        });

        cy.get(page.elements.smartBarBack).click();

        // verify fields
        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--0 ${page.elements.mailHeaderFooterColumnName}`)
            .contains('Header and Footer');

        cy.get(`${page.elements.mailHeaderFooterGridList} ${page.elements.dataGridRow}--1 ${page.elements.mailHeaderFooterColumnName}`)
            .contains('Header and Footer');
    });
});
